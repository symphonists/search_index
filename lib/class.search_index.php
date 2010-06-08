<?

Class SearchIndex {
	
	private static $_entry_manager = null;
	
	/**
	* Set up static members
	*/
	private function assert() {
		if (self::$_entry_manager == null) self::$_entry_manager = new EntryManager(Administration::instance());
	}
	
	/**
	* Returns an array of all indexed sections and their filters
	*/
	public static function getIndexes() {
		$indexes = Symphony::Configuration()->get('indexes', 'search_index');
		return unserialize($indexes);			
	}
	
	public static function saveIndexes($indexes) {
		Symphony::Configuration()->set('indexes', serialize($indexes), 'search_index');
		Administration::instance()->saveConfig();
	}
	
	public function indexEntry($entry, $section) {
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
				$field->buildDSRetrivalSQL($value, $joins, $where, ($filter_type == DS_FILTER_AND ? true : false));

			}
		}			
		
		// run entry though filters
		$entry_prefilter = self::$_entry_manager->fetch($entry, $section, 1, 0, $where, $joins, FALSE, FALSE);
		
		// if no entry found, it didn't pass the pre-filtering
		if (empty($entry_prefilter)) return;
		
		// if entry passes filtering, pass entry_id as a DS filter to the EntryXMLDataSource DS
		$entry = reset($entry_prefilter);
		$entry = $entry['id'];
		
		// create a DS and filter on System ID of the current entry to build the entry's XML			
		$ds = new EntryXMLDataSource(Administration::instance(), null, false);
		$ds->dsParamINCLUDEDELEMENTS = $indexed_sections[$section]['fields'];
		$ds->dsParamFILTERS['id'] = $entry;
		$ds->dsSource = (string)$section;
		
		$param_pool = array();
		$entry_xml = $ds->grab($param_pool);
		
		require_once(TOOLKIT . '/class.xsltprocess.php');

		// get text value of the entry
		$proc = new XsltProcess();
		$data = $proc->process($entry_xml->generate(), file_get_contents(EXTENSIONS . '/search_index/lib/parse-entry.xsl'));
		$data = trim($data);
		
		self::saveEntryIndex($entry, $section, $data);
	}
	
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
	
	public function deleteIndexBySection($section_id) {
		Symphony::Database()->query(
			sprintf(
				"DELETE FROM `tbl_search_index` WHERE `section_id` = %d",
				$section_id
			)
		);
	}
	
	public function deleteIndexByEntry($entry_id) {			
		Symphony::Database()->query(
			sprintf(
				"DELETE FROM `tbl_search_index` WHERE `entry_id` = %d",
				$entry_id
			)
		);
	}
	
	public function wildcardSearchKeywords($string) {
		$string = explode(' ', $string);
		// add wildcard after each word
		foreach($string as &$word) {
			if (!preg_match('/\*$/', $word)) $word .= '*';
		}
		// join words together
		$string = join(' ', $string);
		return $string;
	}
	
}