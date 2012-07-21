<?php
	
	require_once(EXTENSIONS . '/search_index/lib/class.search_index.php');
	
	Class fieldSearch_Index extends Field{	
		
		private $keywords_highlight = '';
		
		/**
		* Class constructor
		*/
		function __construct(){
			parent::__construct();
			$this->_name = __('Search Index');
			$this->_required = FALSE;			
			$this->set('hide', 'no');
		}
		
		/**
		* Allow filtering through a Data Source
		*/
		function canFilter(){
			return TRUE;
		}
		
		/**
		* Process POST data for entry saving
		*/
		public function processRawFieldData($data, &$status, $simulate=FALSE, $entry_id=NULL) {	
			$status = self::__OK__;			
			return array('value' => '');
		}
		
		/**
		* Persist field configuration
		*/
		function commit(){
			// set up standard Field settings
			if(!parent::commit()) return FALSE;
			
			$id = $this->get('id');
			if($id === FALSE) return FALSE;
			
			$fields = array();
			$fields['field_id'] = $id;
			
			// delete existing field configuration
			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			// save new field configuration
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
		}

		/**
		* Building HTML for entry form
		*
		* @param XMLElement $wrapper
		* @param array $data
		* @param boolean $flagWithError
		* @param string $fieldnamePrefix
		* @param string $fieldnamePostfix
		*/
		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
			$value = $data['value'];					
			$label = Widget::Label($this->get('label'));
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', __('Optional')));			
		}
		
		/**
		* Building HTML for section editor
		*
		* @param XMLElement $wrapper
		* @param array $data
		* @param array $errors
		* @param boolean $flagWithError
		* @param string $fieldnamePrefix
		* @param string $fieldnamePostfix
		*/
		public function displayDatasourceFilterPanel(&$wrapper, $data=NULL, $errors=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
			$header = new XMLElement('header');
			$header->appendChild(new XMLElement('h4', $this->get('label')));
			$header->appendChild(new XMLElement('span', $this->name()));
			$wrapper->appendChild($header);
			$label = Widget::Label(__('Value'));
			$label->appendChild(Widget::Input('fields[filter]'.($fieldnamePrefix ? '['.$fieldnamePrefix.']' : '').'['.$this->get('id').']'.($fieldnamePostfix ? '['.$fieldnamePostfix.']' : ''), ($data ? General::sanitize($data) : NULL)));	
			$wrapper->appendChild($label);
		}
		
		/**
		 * Append the formatted xml output of this field as utilized as a data source.
		 *
		 * @param XMLElement $wrapper
		 * @param array $data
		 * @param boolean $encode (optional)
		 * @param string $mode
		 * @param integer $entry_id (optional)
		 */
		public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null) {
			
			$excerpt = Symphony::Database()->fetchVar('data', 0,
				sprintf("SELECT data FROM tbl_search_index WHERE entry_id = %d LIMIT 0, 1", $entry_id)
			);
			
			$excerpt = SearchIndex::parseExcerpt($this->keywords_highlight, $excerpt);
			
			$wrapper->appendChild(
				new XMLElement($this->get('element_name'), $excerpt)
			);
		}
		
		/**
		* Create table to hold field instance's values
		*/		
		public function createTable(){
			return Symphony::Database()->query(			
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `value` double default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `value` (`value`)
				) TYPE=MyISAM;"			
			);
		}
		
		/**
		* Build SQL for Data Source filter
		*
		* @param array $data
		* @param string $joins
		* @param string $where
		* @param boolean $andOperation
		*/
		function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=FALSE){
			$field_id = $this->get('id');
			
			$joins .= " LEFT JOIN `tbl_search_index` AS search_index ON (e.id = search_index.entry_id) ";
			
			if (!is_array($data)) $data = array($data);
			if (is_array($data)) $data = implode(" ", $data);
			
			$mode = !is_null(Symphony::Configuration()->get('mode', 'search_index')) ? Symphony::Configuration()->get('mode', 'search_index') : 'like';
			$mode = strtoupper($mode);
			
			$do_stemming = (Symphony::Configuration()->get('stem-words', 'search_index') == 'yes') ? TRUE : FALSE;
			
			$keywords = SearchIndex::applySynonyms($data);
			$keywords_boolean = SearchIndex::parseKeywordString($keywords, $do_stemming);
			$this->keywords_highlight = trim(implode(' ', $keywords_boolean['highlight']), '"');
			
			switch($mode) {
				
				case 'FULLTEXT':				
					$where .= " AND MATCH(search_index.data) AGAINST ('{$keywords}' IN BOOLEAN MODE) ";
				break;
				
				case 'LIKE':
				case 'REGEXP':
					
					$has_keywords = FALSE;
					$sql_where = '';
					
					// by default, no wildcard separators
					$prefix = '';
					$suffix = '';
					
					// append wildcard for LIKE
					if($mode == 'LIKE') {
						$prefix = $suffix = '%';
					}
					// apply word boundary separator
					if($mode == 'REGEXP') {
						$prefix = '[[:<:]]';
						$suffix = '[[:>:]]';
					}
					
					// all words to include in the query (single words and phrases)
					foreach($keywords_boolean['include-words-all'] as $keyword) {
						$has_keywords = TRUE;
						$keyword_stem = NULL;
						
						$keyword = Symphony::Database()->cleanValue($keyword);
						if($do_stemming) {
							$keyword_stem = Symphony::Database()->cleanValue(PorterStemmer::Stem($keyword));
						}
						
						// if the word can be stemmed, look for the word or the stem version
						if ($do_stemming && ($keyword_stem != $keyword)) {
							$sql_where .= "(search_index.data $mode '$prefix$keyword$suffix' OR search_index.data $mode '$prefix$keyword$suffix') AND ";
						} else {
							$sql_where .= "search_index.data $mode '$prefix$keyword$suffix' AND ";
						}
					}
					
					// all words or phrases that we do not want
					foreach($keywords_boolean['exclude-words-all'] as $keyword) {
						$has_keywords = TRUE;
						$keyword = Symphony::Database()->cleanValue($keyword);
						$sql_where .= "search_index.data NOT $mode '$prefix$keyword$suffix' AND ";
					}
					
					// trim unnecessary boolean conditions from SQL
					$sql_where = preg_replace("/ OR $/", "", $sql_where);
					$sql_where = preg_replace("/ AND $/", "", $sql_where);
					
					if($has_keywords) $where .= " AND " . $sql_where . " ";
					
				break;
			}
			
			return TRUE;
			
		}
						
	}

?>