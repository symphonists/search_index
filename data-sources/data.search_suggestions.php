<?php
	
	require_once(EXTENSIONS . '/search_index/lib/class.search_index.php');
	require_once(TOOLKIT . '/class.datasource.php');
	require_once(TOOLKIT . '/class.fieldmanager.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	
	Class datasourcesearch_suggestions extends Datasource{
		
		public $dsParamROOTELEMENT = 'search-suggestions';
		public $dsParamLIMIT = '1';
		public $dsParamSTARTPAGE = '1';
		
		public function __construct(&$parent, $env=NULL, $process_params=true){
			parent::__construct($parent, $env, $process_params);
		}
		
		public static function sortWordDistance($a, $b) {
			return $a['distance'] > $b['distance'];
		}
		
		public function about(){
			return array(
					'name' => 'Search Index Suggestions',
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
		
		public function grab(&$param_pool) {
			
			$result = new XMLElement($this->dsParamROOTELEMENT);
			
		// Setup
		/*-----------------------------------------------------------------------*/	

			$keywords = (string)$_GET['keywords'];
			$keywords = trim($keywords);
			
			$sort = (string)$_GET['sort'];
			if($sort == '' || $sort == 'alphabetical') {
				$sort = '`keywords`.`keyword` ASC';
			} elseif($sort == 'frequency') {
				$sort = '`frequency` DESC';
			}
			
			if(strlen($keywords) <= 2) return $result;
					

		// Build SQL
		/*-----------------------------------------------------------------------*/	
			
			$sql = sprintf(
				"SELECT
					`keywords`.`keyword`,
					SUM(`entry_keywords`.`frequency`) AS `frequency`
				FROM
					`sym_search_index_keywords` AS `keywords`
					INNER JOIN `sym_search_index_entry_keywords` AS `entry_keywords` ON (`keywords`.`id` = `entry_keywords`.`keyword_id`)
				WHERE
					`keywords`.`keyword` LIKE '%s%%'
				GROUP BY `keywords`.`keyword`
				ORDER BY %s
				LIMIT 0, 50",
				Symphony::Database()->cleanValue($keywords),
				$sort
			);

		
		// Run!
		/*-----------------------------------------------------------------------*/
			
			// get our entries, returns entry IDs
			$words = Symphony::Database()->fetch($sql);
			
			foreach($words as $word) {
				$result->appendChild(
					new XMLElement(
						'word',
						General::sanitize($word['keyword']),
						array(
							'frequency' => $word['frequency'],
							'handle' => Lang::createHandle($word['keyword'])
						)
					)
				);
			}
			
			return $result;
	
	}
}