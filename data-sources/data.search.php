<?php
	
	require_once(EXTENSIONS . '/search_index/lib/class.search_index.php');
	require_once(TOOLKIT . '/class.datasource.php');
	require_once(TOOLKIT . '/class.fieldmanager.php');
	
	Class datasourcesearch extends Datasource{
		
		public $dsParamROOTELEMENT = 'search';
		public $dsParamLIMIT = '20';
		public $dsParamSTARTPAGE = '1';
		public $log = TRUE;
		
		public function __construct(&$parent, $env=NULL, $process_params=true){
			parent::__construct($parent, $env, $process_params);
		}
		
		public function about(){
			return array(
					'name' => 'Search Index',
					'author' => array(
							'name' => 'Nick Dunn',
							'website' => 'http://nick-dunn.co.uk'
						)
					);	
		}
		
		public function getSource(){
			return NULL;
		}
		
		public function allowEditorToParse(){
			return FALSE;
		}
		
		private function errorXML($message) {
			$result = new XMLElement($this->dsParamROOTELEMENT);
			$result->appendChild(new XMLElement('error', $message));
			return $result;
		}
		
		public function grab(&$param_pool) {
			$result = new XMLElement($this->dsParamROOTELEMENT);
			$param_output = array();
			
			$get = $_GET;
			// look for key in GET array if it's specified
			if (Symphony::Configuration()->get('get-param-prefix', 'search_index') != '') {
				$get = $get[Symphony::Configuration()->get('get-param-prefix', 'search_index')];
			}
			
			$param_keywords = Symphony::Configuration()->get('get-param-keywords', 'search_index');
			$param_per_page = Symphony::Configuration()->get('get-param-per-page', 'search_index');
			$param_sort = Symphony::Configuration()->get('get-param-sort', 'search_index');
			$param_direction = Symphony::Configuration()->get('get-param-direction', 'search_index');
			$param_sections = Symphony::Configuration()->get('get-param-sections', 'search_index');
			$param_page = Symphony::Configuration()->get('get-param-page', 'search_index');
			
			$keywords = $get[$param_keywords];
			$this->dsParamLIMIT = (isset($get[$param_per_page]) && (int)$get[$param_per_page] > 0) ? (int)$get[$param_per_page] : $this->dsParamLIMIT;
			$sort = isset($get[$param_sort]) ? $get[$param_sort] : 'score';			
			$direction = isset($get[$param_direction]) ? strtolower($get[$param_direction]) : 'desc';
			$sections = isset($get[$param_sections]) ? $get[$param_sections] : NULL;
			
			if ($sections == NULL && Symphony::Configuration()->get('default-sections', 'search_index') != '') {
				$sections = Symphony::Configuration()->get('default-sections', 'search_index');
			}
			
			$this->dsParamSTARTPAGE = isset($get[$param_page]) ? (int)$get[$param_page] : $this->dsParamSTARTPAGE;
			
			if (is_null($sections)) {
				
				return $this->errorXML('Invalid search sections');
				
			} else {
				
				$section_handles = explode(',', $sections);
				$sections = array();
				
				foreach($section_handles as $handle) {
					$section = Symphony::Database()->fetchRow(0,
						sprintf(
							"SELECT `id`, `name` FROM `tbl_sections` WHERE handle = '%s' LIMIT 1",
							Symphony::Database()->cleanValue($handle)
						)
					);
					if ($section) $sections[$section['id']] = array('handle' => $handle, 'name' => $section['name']);
				}
				
				if (count($sections) == 0) return $this->errorXML('Invalid search sections');
				
			}
			
			if ($sort == 'date') {
				$order_by = "e.creation_date $direction";
			}			
			else if ($sort == 'id') {
				$order_by = "e.id $direction";
			}			
			else {
				$order_by = "score $direction";
			}
			
			$weighting = '';
			$indexed_sections = SearchIndex::getIndexes();

			foreach($indexed_sections as $section_id => $index) {
				$weight = is_null($index['weighting']) ? 2 : $index['weighting'];
				switch ($weight) {
					case 0: $weight = 4; break; // highest
					case 1: $weight = 2; break; // high
					//case 2: $weight = 1; break; // none
					case 3: $weight = 0.5; break; // low
					case 4: $weight = 0.25; break; // lowest
				}
				if ($weight != 1) $weighting .= sprintf("WHEN e.section_id = %d THEN %d \n", $section_id, $weight);
			}
			
			$sql = sprintf(
				"SELECT 
					SQL_CALC_FOUND_ROWS 
					e.id as `entry_id`,
					data,
					e.section_id as `section_id`,
					UNIX_TIMESTAMP(e.creation_date) AS `creation_date`,
					(
						MATCH(index.data) AGAINST ('%1\$s') * 
						CASE
							%2\$s
							ELSE 1
						END
						%3\$s						
					) AS `score`
				FROM
					tbl_search_index as `index`
					JOIN tbl_entries as `e` ON (index.entry_id = e.id)
				WHERE
					MATCH(index.data) AGAINST ('%4\$s' IN BOOLEAN MODE)
					AND e.section_id IN ('%5\$s')
				ORDER BY
					%6\$s
				LIMIT %7\$d, %8\$d",
				
				// keywords				
				Symphony::Database()->cleanValue($keywords),
				$weighting,
				($sort == 'score-recency') ? '/ SQRT(GREATEST(1, DATEDIFF(NOW(), creation_date)))' : '',
				Symphony::Database()->cleanValue(SearchIndex::manipulateKeywords($keywords)),
				
				// list of section IDs
				implode("','", array_keys($sections)),
				
				// order by
				Symphony::Database()->cleanValue($order_by),
				
				// limit start
				max(0, ($this->dsParamSTARTPAGE - 1) * $this->dsParamLIMIT),
				
				// limit
				(int)$this->dsParamLIMIT
			);
			
			//echo $sql;die;
			
			$result->setAttributeArray(
				array(
					'keywords' => General::sanitize($keywords),
					'sort' => $sort,
					'direction' => $direction,
				)
			);
			
			// get our entries!
			$entries = Symphony::Database()->fetch($sql);
			$total_entries = Symphony::Database()->fetchVar('total', 0, 'SELECT FOUND_ROWS() AS `total`');
			
			$result->appendChild(
				General::buildPaginationElement(
					$total_entries,
					ceil($total_entries * (1 / $this->dsParamLIMIT)),
					$this->dsParamLIMIT,
					$this->dsParamSTARTPAGE
				)
			);
			
			$sections_xml = new XMLElement('sections');
			
			foreach($sections as $id => $section) {
				$sections_xml->appendChild(
					new XMLElement(
						'section',
						General::sanitize($section['name']),
						array(
							'id' => $id,
							'handle' => $section['handle']
						)
					)
				);
			}
			$result->appendChild($sections_xml);
						
			foreach($entries as $entry) {
				
				$param_output[] = $entry['entry_id'];
				
				$result->appendChild(
					new XMLElement(
						'entry',
						General::sanitize(
							SearchIndex::parseExcerpt($keywords, $entry['data'])
						),
						array(
							'id' => $entry['entry_id'],
							'section' => $sections[$entry['section_id']]['handle'],
							'score' => round($entry['score'], 3)
						)
					)
				);
			}
			
			// send entry IDs as Output Parameterss
			$param_pool['ds-' . $this->dsParamROOTELEMENT] = $param_output;
			
			$log_sql = sprintf(
				"INSERT INTO `tbl_search_index_logs`
				(date, keywords, sections, page, results, session_id)
				VALUES('%s', '%s', '%s', %d, %d, '%s')",
				date('Y-m-d H:i:s', time()),
				Symphony::Database()->cleanValue($keywords),
				Symphony::Database()->cleanValue(implode(',',$section_handles)),
				$this->dsParamSTARTPAGE,
				$total_entries,
				session_id()
			);
			if ($this->log === TRUE) Symphony::Database()->query($log_sql);
		
			return $result;		

		}
	
	}