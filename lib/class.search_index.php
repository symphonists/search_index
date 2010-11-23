<?php

Class SearchIndex {
	
	private static $_entry_manager = NULL;
	private static $_entry_xml_datasource = NULL;
	
	private static $_where = NULL;
	private static $_joins = NULL;
	
	private static $_context = NULL;
	
	/**
	* Set up static members
	*/
	private function assert() {

		$mode = (isset($_GET['mode']) && strtolower($_GET['mode']) == 'administration' 
				? 'administration' 
				: 'frontend');
		
		self::$_context = ($mode == 'administration' ? Administration::instance() : Frontend::instance());
		
		if (self::$_entry_manager == NULL) self::$_entry_manager = new EntryManager(self::$_context);
		if (self::$_entry_xml_datasource == NULL) self::$_entry_xml_datasource = new EntryXMLDataSource(self::$_context, NULL, FALSE);
	}
	
	/**
	* Returns an array of all indexed sections and their filters
	*/
	public static function getIndexes() {
		$indexes = Symphony::Configuration()->get('indexes', 'search_index');
		$indexes = preg_replace("/\\\/",'',$indexes);
		$unserialised = unserialize($indexes);
		return unserialize($indexes);
	}
	
	/**
	* Save all index configurations to config
	*
	* @param array $indexes
	*/
	public static function saveIndexes($indexes) {
		self::assert();
		Symphony::Configuration()->set('indexes', serialize($indexes), 'search_index');
		self::$_context->saveConfig();
	}
	
	/**
	* Parse the indexable content for an entry
	*
	* @param int $entry
	* @param int $section
	*/
	public function indexEntry($entry, $section, $check_filters=TRUE) {
		self::assert();
		
		if (is_object($entry)) $entry = $entry->get('id');
		if (is_object($section)) $section = $section->get('id');
		
		// get a list of sections that have indexing enabled
		$indexed_sections = self::getIndexes();
		
		// go no further if this section isn't being indexed
		if (!isset($indexed_sections[$section])) return;
		
		// get the current section index config
		$section_index = $indexed_sections[$section];
		
		// only pass entries through filters if we need to. If entry is being sent
		// from the Re-Index AJAX it has already gone through filtering, so no need here
		if ($check_filters === TRUE) {

			if (self::$_where == NULL || self::$_joins == NULL) {
				
				// modified from the core's class.datasource.php
				
				// create filters and build SQL required for each
				if(is_array($section_index['filters']) && !empty($section_index['filters'])) {				
					
					foreach($section_index['filters'] as $field_id => $filter){

						if((is_array($filter) && empty($filter)) || trim($filter) == '') continue;
						
						if(!is_array($filter)){
							$filter_type = DataSource::__determineFilterType($filter);

							$value = preg_split('/'.($filter_type == DS_FILTER_AND ? '\+' : '(?<!\\\\),').'\s*/', $filter, -1, PREG_SPLIT_NO_EMPTY);			
							$value = array_map('trim', $value);

							$value = array_map(array('Datasource', 'removeEscapedCommas'), $value);
						}

						else $value = $filter;

						if(!isset($fieldPool[$field_id]) || !is_object($fieldPool[$field_id]))
							$fieldPool[$field_id] =& self::$_entry_manager->fieldManager->fetch($field_id);

						if($field_id != 'id' && !($fieldPool[$field_id] instanceof Field)){
							throw new Exception(
								__(
									'Error creating field object with id %1$d, for filtering in data source "%2$s". Check this field exists.', 
									array($field_id, $this->dsParamROOTELEMENT)
								)
							);
						}

						if($field_id == 'id') $where = " AND `e`.id IN ('".@implode("', '", $value)."') ";
						else{ 
							if(!$fieldPool[$field_id]->buildDSRetrivalSQL($value, $joins, $where, ($filter_type == DS_FILTER_AND ? true : false))){ $this->_force_empty_result = true; return; }
							if(!$group) $group = $fieldPool[$field_id]->requiresSQLGrouping();
						}
											
					}
				}
				self::$_where = $where;
				self::$_joins = $joins;
			}

			// run entry though filters
			$entry_prefilter = self::$_entry_manager->fetch($entry, $section, 1, 0, self::$_where, self::$_joins, FALSE, FALSE);

			// if no entry found, it didn't pass the pre-filtering
			if (empty($entry_prefilter)) return;

			// if entry passes filtering, pass entry_id as a DS filter to the EntryXMLDataSource DS
			$entry = reset($entry_prefilter);
			$entry = $entry['id'];
			
		}
		
		if (!is_array($entry)) $entry = array($entry);
		
		// create a DS and filter on System ID of the current entry to build the entry's XML			
		#$ds = new EntryXMLDataSource(Administration::instance(), NULL, FALSE);
		self::$_entry_xml_datasource->dsParamINCLUDEDELEMENTS = $indexed_sections[$section]['fields'];
		self::$_entry_xml_datasource->dsParamFILTERS['id'] = implode(',',$entry);
		self::$_entry_xml_datasource->dsSource = (string)$section;
		
		$param_pool = array();
		$entry_xml = self::$_entry_xml_datasource->grab($param_pool);
		
		require_once(TOOLKIT . '/class.xsltprocess.php');
		
		$xml = simplexml_load_string($entry_xml->generate());
		
		foreach($xml->xpath("//entry") as $entry_xml) {
			
			$entry_id = (int)$entry_xml->attributes()->id;
			
			// delete existing index for this entry
			self::deleteIndexByEntry($entry_id);
			
			// get text value of the entry
			$proc = new XsltProcess();
			$data = $proc->process($entry_xml->asXML(), file_get_contents(EXTENSIONS . '/search_index/lib/parse-entry.xsl'));
			$data = trim($data);
			self::saveEntryIndex($entry_id, $section, $data);
		}

	}
	
	/**
	* Store the indexable content for an entry
	*
	* @param int $entry
	* @param int $section
	* @param string $data
	*/
	public function saveEntryIndex($entry_id, $section_id, $data) {
		Symphony::Database()->insert(
			array(
				'entry_id' => $entry_id,
				'section_id' => $section_id,
				'data' => $data
			),
			'tbl_search_index'
		);
	}
	
	/**
	* Delete indexed entry data for a section
	*
	* @param int $section_id
	*/
	public function deleteIndexBySection($section_id) {
		Symphony::Database()->query(
			sprintf(
				"DELETE FROM `tbl_search_index` WHERE `section_id` = %d",
				$section_id
			)
		);
	}
	
	/**
	* Delete indexed entry data for an entry
	*
	* @param int $entry_id
	*/
	public function deleteIndexByEntry($entry_id) {
		Symphony::Database()->query(
			sprintf(
				"DELETE FROM `tbl_search_index` WHERE `entry_id` = %d",
				$entry_id
			)
		);
	}
	
	/**
	* Pre-manipulation of search string
	* 1. Make all words required by prefixing with + (if no +/- already prefixed)
	* 2. Leave "quoted phrases" untouched
	* 3. If enabled in config, append wildcard * to end of words for partial matching
	*
	* @param string $string
	*/
	public function manipulateKeywords($string) {
		
		// replace spaces within quoted phrases
		$string = preg_replace('/"(?:[^\\"]+|\\.)*"/e', "str_replace(' ', 'SEARCH_INDEX_SPACE', '$0')", $string);
		// correct slashed quotes sa a result of above
		$string = stripslashes(trim($string));
		
		$keywords = '';
		
		// get each word
		foreach(explode(' ', $string) as $word) {
			if (!preg_match('/^(\-|\+)/', $word) && !preg_match('/^"/', $word)) {
				if (Symphony::Configuration()->get('append-all-words-required', 'search_index') == 'yes') {
					$word = '+' . $word;
				}
				if (!preg_match('/\*$/', $word) && Symphony::Configuration()->get('append-wildcard', 'search_index') == 'yes') {
					$word = $word . '*';
				}
			}
			$keywords .= $word . ' ';
		}
		
		$keywords = trim($keywords);
		$keywords = preg_replace('/SEARCH_INDEX_SPACE/', ' ', $keywords);
		
		return $keywords;
	}
	
	public static function parseExcerpt($keywords, $text) {
	
		$text = trim($text);
		$text = preg_replace("/\n/", '', $text);
		
		// remove punctuation for highlighting
		$keywords = preg_replace("/[^A-Za-z0-9\s]/", '', $keywords);
	
		$string_length = (Symphony::Configuration()->get('excerpt-length', 'search_index')) ? Symphony::Configuration()->get('excerpt-length', 'search_index') : 200;
		$between_start = $string_length / 2;
		$between_end = $string_length / 2;
		$elipsis = '&#8230;';

		// Extract positive keywords and phrases
		preg_match_all('/ ("([^"]+)"|(?!OR)([^" ]+))/', ' '. $keywords, $matches);
		$keywords = array_merge($matches[2], $matches[3]);
	
		// don't highlight short words
		foreach($keywords as $i => $keyword) {
			if (strlen($keyword) < 3) unset($keywords[$i]);
		}

		// Prepare text
		$text = ' '. strip_tags(str_replace(array('<', '>'), array(' <', '> '), $text)) .' ';
		// no idea what this next line actually does, nothing is harmed if it's simply commented out...
		array_walk($keywords, 'SearchIndex::_parseExcerptReplace');
		$workkeys = $keywords;

		// Extract a fragment per keyword for at most 4 keywords.
		// First we collect ranges of text around each keyword, starting/ending
		// at spaces.
		// If the sum of all fragments is too short, we look for second occurrences.
		$ranges = array();
		$included = array();
		$length = 0;
		while ($length < $string_length && count($workkeys)) {
			foreach ($workkeys as $k => $key) {
				if (strlen($key) == 0) {
					unset($workkeys[$k]);
					unset($keywords[$k]);
					continue;
				}
				if ($length >= $string_length) {
					break;
				}
				// Remember occurrence of key so we can skip over it if more occurrences
				// are desired.
				if (!isset($included[$key])) {
					$included[$key] = 0;
				}
				// Locate a keyword (position $p), then locate a space in front (position
				// $q) and behind it (position $s)
				if (preg_match('/'. $boundary . $key . $boundary .'/iu', $text, $match, PREG_OFFSET_CAPTURE, $included[$key])) {
					$p = $match[0][1];
					if (($q = strpos($text, ' ', max(0, $p - $between_start))) !== FALSE) {
						$end = substr($text, $p, $between_end);
						if (($s = strrpos($end, ' ')) !== FALSE) {
							$ranges[$q] = $p + $s;
							$length += $p + $s - $q;
							$included[$key] = $p + 1;
						}
						else {
							unset($workkeys[$k]);
						}
					}
					else {
						unset($workkeys[$k]);
					}
				}
				else {
					unset($workkeys[$k]);
				}
			}
		}

		// If we didn't find anything, return the beginning.
		if (count($ranges) == 0) {
			if (strlen($text) > $string_length) {
				return substr($text, 0, $string_length) . $elipsis;
			} else {
				return $text;
			}
		}

		// Sort the text ranges by starting position.
		ksort($ranges);

		// Now we collapse overlapping text ranges into one. The sorting makes it O(n).
		$newranges = array();
		foreach ($ranges as $from2 => $to2) {
			if (!isset($from1)) {
				$from1 = $from2;
				$to1 = $to2;
				continue;
			}
			if ($from2 <= $to1) {
				$to1 = max($to1, $to2);
			}
			else {
				$newranges[$from1] = $to1;
				$from1 = $from2;
				$to1 = $to2;
			}
		}
		$newranges[$from1] = $to1;

		// Fetch text
		$out = array();
		foreach ($newranges as $from => $to) {
			$out[] = substr($text, $from, $to - $from);
		}
		$text = (isset($newranges[0]) ? '' : $elipsis) . implode($elipsis, $out) . $elipsis;

		// Highlight keywords. Must be done at once to prevent conflicts ('strong' and '<strong>').
		$text = preg_replace('/'. $boundary .'('. implode('|', $keywords) .')'. $boundary .'/iu', '<strong>\0</strong>', $text);
	
		$text = trim($text);
	
		return $text;
	}
	
	private static function _parseExcerptReplace(&$text) {
		$text = preg_quote($text, '/');
	}
	
}