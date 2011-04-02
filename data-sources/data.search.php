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
				if (Symphony::Configuration()->get('get-param-prefix', 'search_index') == 'param_pool') {
					$get = $this->_env['param'];
				} else {
					$get = $get[Symphony::Configuration()->get('get-param-prefix', 'search_index')];
				}
			}
			
			$param_keywords = Symphony::Configuration()->get('get-param-keywords', 'search_index');
			$param_per_page = Symphony::Configuration()->get('get-param-per-page', 'search_index');
			$param_sort = Symphony::Configuration()->get('get-param-sort', 'search_index');
			$param_direction = Symphony::Configuration()->get('get-param-direction', 'search_index');
			$param_sections = Symphony::Configuration()->get('get-param-sections', 'search_index');
			$param_page = Symphony::Configuration()->get('get-param-page', 'search_index');
			
			$keywords = trim($get[$param_keywords]);
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
					case 2: $weight = 1; break; // none
					case 3: $weight = 0.5; break; // low
					case 4: $weight = 0.25; break; // lowest
				}
				$weighting .= sprintf("WHEN e.section_id = %d THEN %d \n", $section_id, $weight);
			}
			
			// cache keywords as user entered them
			$original_keywords = $keywords;
			// replace synonyms
			$keywords = SearchIndex::applySynonyms($keywords);
			
			$highlight_keywords = '';
			$highlight_keywords = $keywords;
			
			// should we apply word stemming?
			$stem_words = (Symphony::Configuration()->get('stem-words', 'search_index') == 'yes') ? TRUE : FALSE;
			if($stem_words) require_once(EXTENSIONS . '/search_index/lib/class.porterstemmer.php');$
			
			$tmp_keywords = '';
			$tmp_keywords = $keywords;
			
			// we will store the various keywords under these categories
			$boolean_keywords = array(
				'include-phrase' => array(),// "foo bar" or +"foo bar"
				'exclude-phrase' => array(), // -"foo bar"
				'include-word' => array(), // foo or +foo
				'exclude-word' => array(), // -foo
				'highlight' => array() // we can highlight these in the returned excerpts
			);
			
			$matches = array();
			
			// look for phrases, surrounded by double quotes
			while (preg_match("/([-]?)\"([^\"]+)\"/", $tmp_keywords, $matches)) {
				if ($matches[1] == '') {
					$boolean_keywords['include-phrase'][] = $matches[2];
					$boolean_keywords['highlight'][] = $matches[2];
				} else {
					$boolean_keywords['exclude-phrase'][] = $matches[2];
				}
				$tmp_keywords = str_replace($matches[0], '', $tmp_keywords);
			}
			
			$tmp_keywords = strtolower(preg_replace("/[ ]+/", " ", $tmp_keywords));
			$tmp_keywords = trim($tmp_keywords);
			$tmp_keywords = explode(' ', $tmp_keywords);
			
			if ($tmp_keywords == '') {
				$limit = 0;
			} else {
				$limit = count($tmp_keywords);
			}
			
			$i = 0;
			
			//get all words (both include and exlude)
			$tmp_include_words = array();
			while ($i < $limit) {
				if (substr($tmp_keywords[$i], 0, 1) == '+') {
					$tmp_include_words[] = substr($tmp_keywords[$i], 1);
					$boolean_keywords['highlight'][] = substr($tmp_keywords[$i], 1);
					if ($stem_words) $boolean_keywords['highlight'][] = PorterStemmer::Stem(substr($tmp_keywords[$i], 1));
				} else if (substr($tmp_keywords[$i], 0, 1) == '-') {
					$boolean_keywords['exclude-word'][] = substr($tmp_keywords[$i], 1);
				} else {
					$tmp_include_words[] = $tmp_keywords[$i];
					$boolean_keywords['highlight'][] = $tmp_keywords[$i];
					if ($stem_words) $boolean_keywords['highlight'][] = PorterStemmer::Stem($tmp_keywords[$i]);
				}
				$i++;
			}

			//add words from phrases to includes
			foreach ($boolean_keywords['include-phrase'] as $phrase) {
				$phrase = strtolower(preg_replace("/[ ]+/", " ", $phrase));
				$phrase = trim($phrase);
				foreach(explode(' ', $phrase) as $word) {
					//$tmp_include_words[] = $word;
				}
			}

			foreach ($tmp_include_words as $word) {
				if ($word =='') continue;
				$boolean_keywords['include-word'][] = $word;
			}
			
			$include_words = array_merge($boolean_keywords['include-phrase'], $boolean_keywords['include-word']);
			$include_words = array_unique($include_words);
			
			$exclude_words = array_merge($boolean_keywords['exclude-phrase'], $boolean_keywords['exclude-word']);
			$exclude_words = array_unique($exclude_words);
			
			$highlight_keywords = trim(implode(' ', $boolean_keywords['highlight']), '"');
			
			$query_mode = Symphony::Configuration()->get('query-mode', 'search_index') != NULL ? Symphony::Configuration()->get('query-mode', 'search_index') : 'like';
			
			switch($query_mode) {
				
				case 'fulltext':
				
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
						Symphony::Database()->cleanValue($keywords),
						$weighting,
						($sort == 'score-recency') ? '/ SQRT(GREATEST(1, DATEDIFF(NOW(), creation_date)))' : '',
						Symphony::Database()->cleanValue($keywords),
						implode("','", array_keys($sections)),
						Symphony::Database()->cleanValue($order_by),
						max(0, ($this->dsParamSTARTPAGE - 1) * $this->dsParamLIMIT),
						(int)$this->dsParamLIMIT
					);
				
				break;
				
				case 'like':
					
					$sql_locate = '';
					$sql_replace = '';
					$sql_where = '';
										
					foreach($include_words as $keyword) {
						
						$keyword_stem = PorterStemmer::Stem($keyword);
						$keyword_stem_safe = Symphony::Database()->cleanValue($keyword_stem);
						$keyword_safe = Symphony::Database()->cleanValue($keyword);
						
						// also add stems to the query
						if ($stem_words && ($keyword_stem != $keyword)) {
							$sql_where .= "(`index`.`data` LIKE '%" . $keyword_safe . "%' OR `index`.`data` LIKE '%" . $keyword_stem_safe . "%') AND ";
						} else {
							$sql_where .= "`index`.`data` LIKE '%" . $keyword_safe . "%' AND ";
						}
						
						$sql_locate .= "IF(LOCATE('$keyword_safe', LOWER(`data`)) > 0, 1, 0) + ";
						$sql_replace .= "(LENGTH(`data`) - LENGTH(REPLACE(LOWER(`data`),LOWER('$keyword_safe'),''))) / LENGTH('$keyword_safe') + ";
												
					}
					
					foreach($exclude_words as $keyword) {
						$keyword = Symphony::Database()->cleanValue($keyword);
						$sql_where .= "`index`.`data` NOT LIKE '%" . $keyword . "%' AND ";
					}
					
					$sql_locate .= '0';
					$sql_replace .= '0';
					$sql_where = preg_replace("/ OR $/", "", $sql_where);
					$sql_where = preg_replace("/ AND $/", "", $sql_where);
					
					if(preg_match("/^score/", $order_by)) {
						$order_by = preg_replace("/^score/", "(keywords_matched * score)", $order_by);
					}
					
					$sql = sprintf(
						"SELECT SQL_CALC_FOUND_ROWS
						e.id as `entry_id`,
						data,
						e.section_id as `section_id`,
						UNIX_TIMESTAMP(e.creation_date) AS `creation_date`,
						(
							%1\$s
						) AS keywords_matched,
						(
							(%2\$s)
							*
							CASE
								%3\$s
								ELSE 1
							END
							%4\$s
						) AS score
						FROM
						sym_search_index as `index`
						JOIN sym_entries as `e` ON (index.entry_id = e.id)
						WHERE
							%5\$s
							AND e.section_id IN ('%6\$s')
						ORDER BY
							%7\$s
						LIMIT %8\$d, %9\$d",
						$sql_locate,
						$sql_replace,
						$weighting,
						($sort == 'score-recency') ? '/ SQRT(GREATEST(1, DATEDIFF(NOW(), creation_date)))' : '',
						$sql_where,
						implode("','", array_keys($sections)),
						Symphony::Database()->cleanValue($order_by),
						max(0, ($this->dsParamSTARTPAGE - 1) * $this->dsParamLIMIT),
						(int)$this->dsParamLIMIT
					);
				
				break;

			}
			
			$result->setAttributeArray(
				array(
					'keywords' => General::sanitize($keywords),
					'sort' => $sort,
					'direction' => $direction,
				)
			);
			
			// we have search words, check for soundalikes
			if(count($include_words) > 0) {
				
				$sounds_like = array();
				
				foreach($include_words as $word) {
					$soundalikes = Symphony::Database()->fetchCol('keyword', sprintf(
						"SELECT keyword FROM sym_search_index_keywords WHERE SOUNDEX(keyword) = SOUNDEX('%s')",
						Symphony::Database()->cleanValue($word)
					));
					foreach($soundalikes as $soundalike) {
						$distance = levenshtein($soundalike, $word);
						if ($distance == 1) {
							$sounds_like[$word] = $soundalike;
						}
					}
				}
				
				if(count($sounds_like) > 0) {
					$alternative_spelling = new XMLElement('alternative-keywords');
					foreach($sounds_like as $word => $soundalike) {
						$alternative_spelling->appendChild(
							new XMLElement('keyword', NULL, array(
								'original' => $word,
								'alternative' => $soundalike
							))
						);
					}
					$result->appendChild($alternative_spelling);
				}
				
			}
			
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
			
			$build_entries = (Symphony::Configuration()->get('build-entries', 'search_index') == 'yes') ? TRUE : FALSE;
			
			if($build_entries) {
				$em = new EntryManager(Frontend::instance());
				$fm = new FieldManager(Frontend::instance());
				$field_pool = array();
			}
			
			foreach($entries as $entry) {
				
				$param_output[] = $entry['entry_id'];
				
				$entry_xml = new XMLElement(
					'entry',
					NULL,
					array(
						'id' => $entry['entry_id'],
						'section' => $sections[$entry['section_id']]['handle'],
						//'score' => round($entry['score'], 3)
					)
				);
				
				$entry_xml->appendChild(
					new XMLElement(
						'excerpt',
						General::sanitize(
							SearchIndex::parseExcerpt($highlight_keywords, preg_replace("/[\s]{2,}/", '', $entry['data']))
						)
					)
				);
				
				if($build_entries) {
					$e = reset($em->fetch($entry['entry_id']));
					$data = $e->getData();
					foreach($data as $field_id => $values){
						if(!isset($field_pool[$field_id]) || !is_object($field_pool[$field_id])) {
							$field_pool[$field_id] = $em->fieldManager->fetch($field_id);
						}
						$field_pool[$field_id]->appendFormattedElement($entry_xml, $values, false, NULL, $e->get('id'));
					}
				}
				
				$result->appendChild($entry_xml);
			}
			
			// send entry IDs as Output Parameterss
			$param_pool['ds-' . $this->dsParamROOTELEMENT] = $param_output;
			
			if ($this->log === TRUE) {
				
				// has this search (keywords+sections) already been logged this session?
				$already_logged = Symphony::Database()->fetch(sprintf(
					"SELECT * FROM `tbl_search_index_logs` WHERE keywords='%s' AND sections='%s' AND session_id='%s'",
					Symphony::Database()->cleanValue($original_keywords), Symphony::Database()->cleanValue(implode(',',$section_handles)), session_id()
				));
				
				$log_sql = sprintf(
					"INSERT INTO `tbl_search_index_logs`
					(date, keywords, keywords_manipulated, sections, page, results, session_id)
					VALUES('%s', '%s', '%s', '%s', %d, %d, '%s')",
					date('Y-m-d H:i:s', time()),
					Symphony::Database()->cleanValue($original_keywords),
					Symphony::Database()->cleanValue($keywords),
					Symphony::Database()->cleanValue(implode(',',$section_handles)),
					$this->dsParamSTARTPAGE,
					$total_entries,
					session_id()
				);
				
				Symphony::Database()->query($log_sql);
				
			}
		
			return $result;		

		}
	
	}