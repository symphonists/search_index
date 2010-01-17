<?php

	require_once(TOOLKIT . '/class.datasource.php');
	
	class Extension_Search_Index extends Extension {
		
		public function about() {
			return array(
				'name'			=> 'Search Index',
				'version'		=> '0.1',
				'release-date'	=> '2010-01-17',
				'author'		=> array(
					'name'			=> 'Nick Dunn'
				),
				'description' => 'Index text content of entries for efficient fulltext search.'
			);
		}
		
		public function install(){
			return $this->_Parent->Database->query(
				"CREATE TABLE `sym_search_index` (
				  `id` int(11) NOT NULL auto_increment,
				  `entry_id` int(11) NOT NULL,
				  `data` text,
				  PRIMARY KEY (`id`),
				  FULLTEXT KEY `data` (`data`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8"
			);
		}		
				
		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/publish/new/',
					'delegate'	=> 'EntryPostCreate',
					'callback'	=> 'saveEntry'
				),				
				array(
					'page'		=> '/publish/edit/',
					'delegate'	=> 'EntryPostEdit',
					'callback'	=> 'saveEntry'
				),
				array(
					'page'		=> '/publish/',
					'delegate'	=> 'Delete',
					'callback'	=> 'deleteEntry'
				),
			);
		}
		
		/*public function fetchNavigation() {
			return array(
				array(
					'location'	=> 'Blueprints',
					'name'		=> 'XML Importers',
					'link'		=> '/importers/'
				),
			);
		}*/
		
		public function getIndexedSections() {
			$sections = array();
			
			// TODO: these should be built from within the UI
			//$section[id]['fields'] = array('handle1', 'handle2');
			//$section[id]['filters'] = array('id' => 'filter');
			
			$sections[2]['fields'] = array('title', 'content');
			$sections[2]['filters'] = array(
				'19' => 'yes'
			);
			
			return $sections;
			
		}
		
		private function __determineFilterType($value){
			return (false === strpos($value, '+') ? DS_FILTER_OR : DS_FILTER_AND);
		}
		
		public function saveEntry($context) {
			$section = $context['section'];
			$entry = $context['entry'];
			
			// get a list of sections that have indexing enabled
			$indexed_sections = $this->getIndexedSections();
			
			// go no further if this section isn't being indexed
			if (!isset($indexed_sections[$section->get('id')])) return;
			
			// delete existing cached text
			Symphony::Database()->query(
				sprintf(
					"DELETE FROM `tbl_search_index` WHERE `entry_id` = %d",
					$entry->get('id')
				)
			);
			
			// get the current section index config
			$section_index = $indexed_sections[$section->get('id')];
			
			// build WHERE and JOINs to see if entry should be index (passes pre-filtering)
			$entryManager = new EntryManager(Administration::instance());
			
			// modified from class.datasource.php
			if(is_array($section_index['filters']) && !empty($section_index['filters'])) {				
				foreach($section_index['filters'] as $field_id => $filter){

					if((is_array($filter) && empty($filter)) || trim($filter) == '') continue;

					if(!is_array($filter)){
						$filter_type = $this->__determineFilterType($filter);

						$value = preg_split('/'.($filter_type == DS_FILTER_AND ? '\+' : '(?<!\\\\),').'\s*/', $filter, -1, PREG_SPLIT_NO_EMPTY);			
						$value = array_map('trim', $value);

						$value = array_map(array('Datasource', 'removeEscapedCommas'), $value);
					}

					else $value = $filter;

					$field = $entryManager->fieldManager->fetch($field_id);
					$field->buildDSRetrivalSQL($value, $joins, $where, ($filter_type == DS_FILTER_AND ? true : false));

				}
			}
			
			$entry_prefilter = $entryManager->fetch($entry->get('id'), $section->get('id'), 1, 0, $where, $joins);
			
			// return if fails pre-filtering
			if (empty($entry_prefilter)) return;
			
			// if entry passes pass entry_id as a DS filter to the EntryXMLDataSource DS
			$entry = reset($entry_prefilter);
			
			// create a DS and filter on System ID of the current entry to build the entry's XML			
			$ds = new EntryXMLDataSource(Administration::instance(), null, false);
			$ds->dsParamINCLUDEDELEMENTS = $indexed_sections[$section->get('id')]['fields'];
			$ds->dsParamFILTERS['id'] = $entry->get('id');
			$ds->dsSource = (string)$section->get('id');
			
			$param_pool = array();
			$entry_xml = $ds->grab($param_pool);
			
			$stylesheet =
				'<?xml version="1.0" encoding="UTF-8" ?>
				<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
				<xsl:output encoding="UTF-8" indent="yes" method="xml" omit-xml-declaration="yes" />

					<xsl:template match="/">
						<xsl:apply-templates select="//entry"/>
					</xsl:template>
					
					<xsl:template match="*">
						<xsl:value-of select="concat(text(), \' \')"/>
						<xsl:apply-templates select="*"/>
					</xsl:template>

				</xsl:stylesheet>';
			
			// get text value of the entry
			$proc = new XsltProcess;
			$data = trim($proc->process($entry_xml->generate(), $stylesheet));
			
			// store in index
			Symphony::Database()->insert(
				array(
					'entry_id' => $entry->get('id'),
					'data' => $data
				),
				'tbl_search_index'
			);	
			
		}
		
		public function deleteEntry($context) {			
			// delete existing cached text
			Symphony::Database()->query(
				sprintf(
					"DELETE FROM `tbl_search_index` WHERE `entry_id` = %d",
					$context['entry_id']
				)
			);
		}
		
	}
	
	Class EntryXMLDataSource extends Datasource{
		
		public $dsParamROOTELEMENT = 'entries';
		public $dsSource = null;
		
		public $dsParamORDER = 'desc';
		public $dsParamLIMIT = '1';
		public $dsParamREDIRECTONEMPTY = 'no';
		public $dsParamSORT = 'system:id';
		public $dsParamSTARTPAGE = '1';		
		
		public function __construct(&$parent, $env=NULL, $process_params=true){
			parent::__construct($parent, $env, $process_params);
		}
		
		public function getSource(){
			return $this->dsSource;
		}
		
		public function grab(&$param_pool){

			$result = new XMLElement($this->dsParamROOTELEMENT);
			
			try{
				include(TOOLKIT . '/data-sources/datasource.section.php');
			}
			catch(Exception $e){
				$result->appendChild(new XMLElement('error', $e->getMessage()));
				return $result;
			}
			if($this->_force_empty_result) $result = $this->emptyXMLSet();

			return $result;		

		}
		
	}
	
?>
