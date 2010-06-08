<?php
	
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	
	require_once(EXTENSIONS . '/search_index/lib/class.search_index.php');
	
	class contentExtensionSearch_IndexReindex extends AdministrationPage {
		protected $_errors = array();
		
		public function __construct(&$parent){
			parent::__construct($parent);
			
			$this->_uri = URL . '/symphony/extension/search_index';
			
			$sectionManager = new SectionManager(Administration::instance());
			$this->_entryManager = new EntryManager(Administration::instance());
				
			$this->_sections = $sectionManager->fetch(NULL, 'ASC', 'name');
			$this->_section = null;
			
			$this->_indexes = SearchIndex::getIndexes();
			$this->_index = null;
		}
		
		public function build($context) {
			$this->__setContext($_GET['section']);
			parent::build($context);
		}
		
		private function __setContext($section_id) {
			$this->_index = $this->_indexes[$section_id];
			foreach($this->_sections as $s) {
				if ($s->get('id') == $section_id) $this->_section = $s;
			}
		}
		
		public function __viewIndex() {
			
			// create a DS and filter on System ID of the current entry to build the entry's XML			
			$ds = new ReindexDataSource(Administration::instance(), null, false);
			$ds->dsSource = (string)$_GET['section'];
			$ds->dsParamFILTERS = $this->_index['filters'];
			
			$param_pool = array();
			$grab_xml = $ds->grab($param_pool);
			
			$xml = $grab_xml->generate();

			$dom = new DomDocument();
			$dom->loadXML($xml);
			$xpath = new DomXPath($dom);
			
			foreach($xpath->query("//entry") as $entry) {
				$context = (object)array(
					'section' => $this->_section,
					'entry' => reset($this->_entryManager->fetch($entry->getAttribute('id')))
				);
				// TODO: move to SearchIndex from driver!!
				SearchIndex::indexEntry($context->entry, $context->section);
			}
			
			header('Content-type: text/xml');
			echo $xml;
			exit;

		}
	}