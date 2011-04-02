<?php

Class SearchIndex {
	
	private static $_entry_manager = NULL;
	private static $_entry_xml_datasource = NULL;
	
	private static $_where = NULL;
	private static $_joins = NULL;
	
	private static $_keyword_cache = array();
	
	/**
	* Set up static members
	*/
	private function assert() {
		require_once(TOOLKIT . '/class.entrymanager.php');
		if (self::$_entry_manager == NULL) self::$_entry_manager = new EntryManager(Symphony::Engine());
		if (self::$_entry_xml_datasource == NULL) self::$_entry_xml_datasource = new EntryXMLDataSource(Symphony::Engine(), NULL, FALSE);
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
		Symphony::Configuration()->set('indexes', stripslashes(serialize($indexes)), 'search_index');
		Symphony::Engine()->saveConfig();
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
		// stores the full entry text
		Symphony::Database()->insert(
			array(
				'entry_id' => $entry_id,
				'section_id' => $section_id,
				'data' => $data
			),
			'tbl_search_index'
		);
		// stores the entry text keywords, one row per word
		self::saveEntryKeywords($entry_id, $data);
	}
	
	public function saveEntryKeywords($entry_id, $data) {
		
		require_once(EXTENSIONS . '/search_index/lib/strip_punctuation.php');
		
		// remove as much crap as possible
		$data = strip_tags($data);
		$data = strtolower($data);
		$data = utf8_encode($data);
		$data = preg_replace('~&#x([0-9a-f]+);~ei', 'chr(hexdec("\\1"))', $data);
	    $data = preg_replace('~&#([0-9]+);~e', 'chr("\\1")', $data);
		$data = strip_punctuation($data);
		
		$words = explode(' ', trim($data));
		$words = array_unique($words);
		
		// store words to log this time around
		$log_keywords = array();
		
		foreach($words as $word) {
			$word = trim($word);
			
			// exclude words that are too short or too long
			if(strlen($word) >= (int)Symphony::Configuration()->get('max-word-length', 'search_index') || strlen($word) < (int)Symphony::Configuration()->get('min-word-length', 'search_index')) {
				continue;
			}
			
			// have we already parsed this word in this process?
			if(isset(self::$_keyword_cache[$word])) {
				$log_keywords[$word] = self::$_keyword_cache[$word];
				continue;
			}
			
			// does this keyword exist in the database already? get its ID
			$keyword_id = Symphony::Database()->fetchVar('id', 0, sprintf("SELECT id FROM `tbl_search_index_keywords` WHERE `keyword` = '%s'", Symphony::Database()->cleanValue($word)));
			if(is_null($keyword_id)) {
				// if it doesn't exist, we need to insert and get its ID
				Symphony::Database()->insert(array('keyword' => $word), 'tbl_search_index_keywords');
				$keyword_id = Symphony::Database()->getInsertID();
			}
			
			// cache the word
			self::$_keyword_cache[$word] = $keyword_id;
			$log_keywords[$word] = $keyword_id;
		}
		
		// no words to log
		if(count($log_keywords) == 0) return;
		
		// delete keyword associations for this entry
		Symphony::Database()->query(sprintf("DELETE FROM `tbl_search_index_entry_keywords` WHERE `entry_id` = %d", $entry_id));
		
		// add all the new word associations in one batch (MUCH faster than an INSERT per word!)
		$insert = "INSERT INTO tbl_search_index_entry_keywords (entry_id, keyword_id, frequency) VALUES ";
		foreach($log_keywords as $keyword => $keyword_id) {
			$insert .= sprintf("(%d, %d, '%s'),", $entry_id, $keyword_id, substr_count($data, $keyword));
		}
		$insert = trim($insert, ',');
		Symphony::Database()->query($insert);
		
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
	
	/**
	* Returns an array of all synonyms
	*/
	public static function getSynonyms() {
		$synonyms = Symphony::Configuration()->get('synonyms', 'search_index');
		//$indexes = preg_replace("/\\\/",'',$synonyms);
		$synonyms = unserialize($synonyms);
		if (!is_array($synonyms)) $synonyms = array();
		uasort($synonyms, array('SearchIndex', 'sortSynonymsCallback'));
		return $synonyms;
	}
	
	/**
	* Save all synonyms to config
	*
	* @param array $synonyms
	*/
	public static function saveSynonyms($synonyms) {
		self::assert();
		Symphony::Configuration()->set('synonyms', stripslashes(serialize($synonyms)), 'search_index');
		Symphony::Engine()->saveConfig();
	}
	
	private static function sortSynonymsCallback($a, $b) {
		return strcmp($a['word'], $b['word']);
	}
	
	public static function applySynonyms($keywords) {
		
		$keywords = explode(' ', $keywords);
		$synonyms = self::getSynonyms();
		
		$keywords_manipulated = '';
		
		foreach($keywords as $word) {
			$boolean_characters = array();
			preg_match('/^(\-|\+)/', $word, $boolean_characters);
			$word = strtolower(trim(preg_replace('/^(\-|\+)/', '', $word)));
			
			foreach($synonyms as $synonym) {
				$synonym_terms = explode(',', $synonym['synonyms']);
				foreach($synonym_terms as $s) {
					$s = strtolower(trim($s));
					// replace word with synonym replace word
					if ($s == $word) $word = $synonym['word'];
				}
			}
			
			// add boolean character back in front of word
			if (count($boolean_characters) > 0) $word = $boolean_characters[0] . $word;
			$keywords_manipulated .= $word . ' ';
		}
		
		return trim($keywords_manipulated);
		
	}
	
	public static function countLogs($filter_keywords) {
		return (int)Symphony::Database()->fetchVar('total', 0, sprintf("SELECT COUNT(*) AS `total` FROM (%s) as `temp`", self::getLogsSQL($filter_keywords)));
	}
	
	private static function getLogsSQL($filter_keywords) {
		$sql = sprintf(
			"SELECT id, keywords, keywords_manipulated, date, sections, results, MAX(page) as `depth`, session_id FROM `sym_search_index_logs` %s GROUP BY keywords, session_id",
			($filter_keywords ? "WHERE keywords LIKE '%" . $filter_keywords . "%'" : '')
		);
		return $sql;
	}
	
	public function getLogs($sort_column='date', $sort_direction='desc', $page=1, $filter_keywords) {
		$page_size = (int)Symphony::Configuration()->get('pagination_maximum_rows', 'symphony');
		$start = ($page - 1) * $page_size;
		$sql = sprintf(
			"%s
			ORDER BY %s %s
			LIMIT %d, %d",
			self::getLogsSQL($filter_keywords),
			$sort_column,
			$sort_direction,
			$start,
			$page_size
		);
		return Symphony::Database()->fetch($sql);
	}
	
	public function getStatsCount($statistic, $filter_keywords) {
		
		$filter = ($filter_keywords ? "WHERE keywords LIKE '%" . $filter_keywords . "%'" : '');
		
		switch($statistic) {
			case 'unique-users':
				return (int)Symphony::Database()->fetchVar('total', 0, sprintf(
					"SELECT COUNT(DISTINCT(session_id)) as `total` FROM `sym_search_index_logs` %s", $filter
				));
			break;
			case 'unique-searches':
				return (int)Symphony::Database()->fetchVar('total', 0, sprintf(
					"SELECT COUNT(*) as `total` FROM (SELECT id FROM `sym_search_index_logs` %s GROUP BY keywords, session_id) as `temp`", $filter
				));
			break;
			case 'unique-terms':
				return (int)Symphony::Database()->fetchVar('total', 0, sprintf(
					"SELECT COUNT(DISTINCT(keywords)) as `total` FROM `sym_search_index_logs` %s", $filter
				));
			break;
			case 'average-results':
				return (int)Symphony::Database()->fetchVar('total', 0, sprintf(
					"SELECT AVG(`temp`.`average`) as `total` FROM (SELECT results as `average` FROM `sym_search_index_logs` %s GROUP BY keywords, session_id) as `temp`", $filter
				));
			break;
			
		}
	}
}