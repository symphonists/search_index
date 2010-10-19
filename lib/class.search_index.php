<?php

Class SearchIndex {
	
	private static $_entry_manager = NULL;
	private static $_entry_xml_datasource = NULL;
	
	private static $_where = NULL;
	private static $_joins = NULL;
	
	/**
	* Set up static members
	*/
	private function assert() {
		if (self::$_entry_manager == NULL) self::$_entry_manager = new EntryManager(Administration::instance());
		if (self::$_entry_xml_datasource == NULL) self::$_entry_xml_datasource = new EntryXMLDataSource(Administration::instance(), NULL, FALSE);
	}
	
	/**
	* Returns an array of all indexed sections and their filters
	*/
	public static function getIndexes() {
		$indexes = Symphony::Configuration()->get('indexes', 'search_index');
		return unserialize($indexes);			
	}
	
	/**
	* Save all index configurations to config
	*
	* @param array $indexes
	*/
	public static function saveIndexes($indexes) {
		Symphony::Configuration()->set('indexes', serialize($indexes), 'search_index');
		Administration::instance()->saveConfig();
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

		// delete existing index for this entry
		self::deleteIndexByEntry($entry);
		
		// get the current section index config
		$section_index = $indexed_sections[$section];
		
		// only pass entries through filters if we need to. If entry is being sent
		// from the Re-Index AJAX it has already gone through filtering, so no need here
		if ($check_filters === TRUE) {
			
			if (self::$_where == NULL || self::$_joins == NULL) {
				// modified from class.datasource.php
				// create filters and build SQL required for each
				if(is_array($section_index['filters']) && !empty($section_index['filters'])) {				

					foreach($section_index['filters'] as $field_id => $filter){

						if((is_array($filter) && empty($filter)) || trim($filter) == '') continue;

						if(!is_array($filter)){
							$filter_type = DataSource::__determineFilterType($filter);
							$value = preg_split('/'.($filter_type == DS_FILTER_AND ? '\+' : ',').'\s*/', $filter, -1, PREG_SPLIT_NO_EMPTY);			
							$value = array_map('trim', $value);
						} else {
							$value = $filter;
						}

						$field = self::$_entry_manager->fieldManager->fetch($field_id);
						$field->buildDSRetrivalSQL($value, $joins, $where, ($filter_type == DS_FILTER_AND ? TRUE : FALSE));

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
			
			// get text value of the entry
			$proc = new XsltProcess();
			$data = $proc->process($entry_xml->asXML(), file_get_contents(EXTENSIONS . '/search_index/lib/parse-entry.xsl'));
			$data = trim($data);

			self::saveEntryIndex((int)$entry_xml->attributes()->id, $section, $data);
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
	* Append wildcard character to the words of a search string
	*
	* @param string $string
	*/
	public function manipulateKeywords($string) {
		if (Symphony::Configuration()->get('append-wildcard', 'search_index') != 'yes') return $string;
		
		// add + in front of any word that doesn't have +/-
		// EXCEPT if words are within quotes, don't mess.
		
		// replace spaces within quoted phrases
		$string = preg_replace('/"(?:[^\\"]+|\\.)*"/e', "str_replace(' ', 'SEARCH_INDEX_SPACE', '$0')", $string);
		// correct slashed quotes sa a result of above
		$string = stripslashes(trim($string));
		
		$keywords = '';
		
		// get each word
		foreach(explode(' ', $string) as $word) {
			if (!preg_match('/^(\-|\+)/', $word) && !preg_match('/^"/', $word)) {
				$keywords .= '+' . $word;
			} else {
				$keywords .= $word;
			}
			$keywords .= ' ';
		}
		
		$keywords = trim($keywords);
		$keywords = preg_replace('/SEARCH_INDEX_SPACE/', ' ', $keywords);
		
		return $keywords;
	}
	
}