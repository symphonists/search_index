<?php

	require_once(TOOLKIT . '/class.datasource.php');
	require_once(TOOLKIT . '/class.fieldmanager.php');
	
	Class datasourcesearch extends Datasource{
		
		public $dsParamROOTELEMENT = 'search';
		public $dsParamLIMIT = '20';
		public $dsParamSTARTPAGE = '1';
		
		public function __construct(&$parent, $env=NULL, $process_params=true){
			parent::__construct($parent, $env, $process_params);
		}
		
		public function about(){
			return array(
					 'name' => 'Search',
					 'author' => array(
							'name' => 'Nick Dunn',
							'website' => 'http://symphony-demo',
							'email' => 'nick.dunn@airlock.com'),
					 'version' => '1.0',
					 'release-date' => '2009-11-12T08:14:58+00:00');	
		}
		
		public function getSource(){
			return null;
		}
		
		public function allowEditorToParse(){
			return false;
		}
		
		private function errorXML($message) {
			$result = new XMLElement($this->dsParamROOTELEMENT);
			$result->appendChild(new XMLElement('error', $message));
			return $result;
		}
		
		public function grab(&$param_pool) {
			$result = new XMLElement($this->dsParamROOTELEMENT);
			$param_output = array();
			
			$keywords = $_GET['keywords'];
			$this->dsParamLIMIT = (isset($_GET['per-page']) && (int)$_GET['per-page'] > 0) ? (int)$_GET['per-page'] : $this->dsParamLIMIT;
			$sort = isset($_GET['sort']) ? $_GET['sort'] : 'score';
			$direction = isset($_GET['direction']) ? strtolower($_GET['direction']) : 'desc';
			$sections = isset($_GET['sections']) ? $_GET['sections'] : null;
			
			$this->dsParamSTARTPAGE = isset($_GET['page']) ? (int)$_GET['page'] : $this->dsParamSTARTPAGE;
			
			if (is_null($sections)) {
				return $this->errorXML('Invalid search sections.');
			} else {
				$section_handles = explode(',', $sections);
				$sections = array();
				foreach($section_handles as $handle) {
					$section = Symphony::Database()->fetchRow(0, sprintf(
							"SELECT `id`, `name` FROM `tbl_sections` WHERE handle = '%s' LIMIT 1", $handle
						)
					);
					if ($section) $sections[$section['id']] = array('handle' => $handle, 'name' => $section['name']);
				}
				if (count($sections) == 0) return $this->errorXML('Invalid search sections.');
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
			
			$sql = sprintf(
				"SELECT
					SQL_CALC_FOUND_ROWS 
					MATCH(index.data) AGAINST ('%1\$s' IN BOOLEAN MODE) AS `score`,
					e.id as `entry_id`,
					e.section_id as `section_id`,
					UNIX_TIMESTAMP(e.creation_date) AS `creation_date`
				FROM
					sym_search_index as `index`
					JOIN sym_entries as `e` ON (index.entry_id = e.id)
				WHERE
					MATCH(index.data) AGAINST ('%1\$s' IN BOOLEAN MODE)
					AND e.section_id IN ('%2\$s')
				ORDER BY
					%3\$s
				LIMIT %4\$d, %5\$d",
				
				Symphony::Database()->cleanValue($keywords),
				implode("','", array_keys($sections)),
				Symphony::Database()->cleanValue($order_by),
				max(0, ($this->dsParamSTARTPAGE - 1) * $this->dsParamLIMIT),
				(int)$this->dsParamLIMIT
			);
			
			$result->setAttributeArray(
				array(
					'keywords' => General::sanitize($keywords),
					'sort' => $sort,
					'direction' => $direction,
				)
			);
			
			$entries = Symphony::Database()->fetch($sql);
			
			$total_entries = Symphony::Database()->fetchVar('total', 0, 'SELECT FOUND_ROWS() AS `total`');
			$result->appendChild(
				General::buildPaginationElement(
					$total_entries,
					ceil($total_entries * (1/$this->dsParamLIMIT)),
					$this->dsParamLIMIT,
					$this->dsParamSTARTPAGE
				)
			);
			
			$sections_xml = new XMLElement('sections');
			foreach($sections as $id => $section) {
				$sections_xml->appendChild(new XMLElement('section', General::sanitize($section['name']), array('id' => $id, 'handle' => $section['handle'])));
			}
			$result->appendChild($sections_xml);
			
			$highest_score = null;
			
			foreach($entries as $entry) {
				
				if (is_null($highest_score)) $highest_score = $entry['score'];
				
				$param_output[] = $entry['entry_id'];
				$result->appendChild(
					new XMLElement('entry', null, array(
						'id' => $entry['entry_id'],
						'section' => $sections[$entry['section_id']]['handle'],
						'score' => (int)(($entry['score'] / $highest_score) * 100)
					))
				);
			}
			
			$param_pool['ds-' . $this->dsParamROOTELEMENT] = implode(', ', $param_output);
		
			return $result;		

		}
		
	}