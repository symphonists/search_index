<?php
	
	require_once(EXTENSIONS . '/search_index/lib/class.search_index.php');
	
	Class fieldSearch_Index extends Field{	
		
		function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'Search Index';
			$this->_required = false;			
			$this->set('hide', 'no');
		}
		
		function canFilter(){
			return true;
		}
		
		public function fetchIncludableElements(){
			return false;
		}
		
		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=null) {	
			$status = self::__OK__;			
			return array('value' => '');
		}
		
		function commit(){
			if(!parent::commit()) return false;			
			$id = $this->get('id');
			if($id === false) return false;			
			$fields = array();			
			$fields['field_id'] = $id;			
			$this->_engine->Database->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			return $this->_engine->Database->insert($fields, 'tbl_fields_' . $this->handle());			
		}

		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
			$value = $data['value'];					
			$label = Widget::Label($this->get('label'));
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', 'Optional'));			
		}

		public function displayDatasourceFilterPanel(&$wrapper, $data=NULL, $errors=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
			$wrapper->appendChild(new XMLElement('h4', $this->get('label') . ' <i>'.$this->Name().'</i>'));
			$label = Widget::Label('Value');
			$label->appendChild(Widget::Input('fields[filter]'.($fieldnamePrefix ? '['.$fieldnamePrefix.']' : '').'['.$this->get('id').']'.($fieldnamePostfix ? '['.$fieldnamePostfix.']' : ''), ($data ? General::sanitize($data) : NULL)));	
			$wrapper->appendChild($label);
		}
				
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
		
		function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){
			$field_id = $this->get('id');
			
			if (!is_array($data)) $data = array($data);
			
			foreach ($data as &$value) {
				$value = SearchIndex::wildcardSearchKeywords($this->cleanValue($value));
			}
			
			$this->_key++;
			$data = implode("', '", $data);
			
			$joins .= " LEFT JOIN `tbl_search_index` AS search_index ON (e.id = search_index.entry_id) ";			
			$where .= " AND MATCH(search_index.data) AGAINST ('{$data}' IN BOOLEAN MODE) ";
			
			return true;
			
		}
						
	}

?>