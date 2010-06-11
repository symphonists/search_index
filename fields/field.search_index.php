<?php
	
	require_once(EXTENSIONS . '/search_index/lib/class.search_index.php');
	
	Class fieldSearch_Index extends Field{	
		
		/**
		* Class constructor
		*/
		function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'Search Index';
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
		* Output elements for Data Source XML
		*/
		public function fetchIncludableElements(){
			return FALSE;
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
			$this->_engine->Database->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			// save new field configuration
			return $this->_engine->Database->insert($fields, 'tbl_fields_' . $this->handle());
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
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', 'Optional'));			
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
			$wrapper->appendChild(new XMLElement('h4', $this->get('label') . ' <i>'.$this->Name().'</i>'));
			$label = Widget::Label('Value');
			$label->appendChild(Widget::Input('fields[filter]'.($fieldnamePrefix ? '['.$fieldnamePrefix.']' : '').'['.$this->get('id').']'.($fieldnamePostfix ? '['.$fieldnamePostfix.']' : ''), ($data ? General::sanitize($data) : NULL)));	
			$wrapper->appendChild($label);
		}
		
		/**
		* Create table to hold field instance's values
		*/		
		public function createTable(){
			return $this->Database->query(			
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
			
			if (!is_array($data)) $data = array($data);
			
			foreach ($data as &$value) {
				$value = SearchIndex::wildcardSearchKeywords($this->cleanValue($value));
			}
			
			$this->_key++;
			$data = implode("', '", $data);
			
			$joins .= " LEFT JOIN `tbl_search_index` AS search_index ON (e.id = search_index.entry_id) ";			
			$where .= " AND MATCH(search_index.data) AGAINST ('{$data}' IN BOOLEAN MODE) ";
			
			return TRUE;
			
		}
						
	}

?>