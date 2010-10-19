<?php
	
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	
	require_once(EXTENSIONS . '/search_index/lib/class.search_index.php');
	
	class contentExtensionSearch_IndexReindex extends AdministrationPage {
		
		public function __construct(&$parent){
			parent::__construct($parent);
			
			$sectionManager = new SectionManager(Administration::instance());
			$this->_entryManager = new EntryManager(Administration::instance());
			
			// cache array of all sections
			$this->_sections = $sectionManager->fetch(NULL, 'ASC', 'name');
			$this->_section = null;
			
			// cache array of all indexes
			$this->_indexes = SearchIndex::getIndexes();
			$this->_index = null;
		}
		
		public function build($context) {
			$this->__setContext((int)$_GET['section']);
			parent::build($context);
		}
		
		/**
		* Sets the context of the page to the desired index (indexed by section ID)
		*
		* @param int $section_id
		*/
		private function __setContext($section_id) {
			$this->_index = $this->_indexes[$section_id];
			foreach($this->_sections as $s) {
				if ($s->get('id') == $section_id) $this->_section = $s;
			}
		}
		
		public function __viewIndex() {
			
			// create a DS and filter on System ID of the current entry to build the entry's XML			
			$ds = new ReindexDataSource(Administration::instance(), NULL, FALSE);
			$ds->dsSource = (string)$_GET['section'];
			$ds->dsParamFILTERS = $this->_index['filters'];
			
			$param_pool = array();
			$grab_xml = $ds->grab($param_pool);
			
			$xml = $grab_xml->generate();

			$dom = new DomDocument();
			$dom->loadXML($xml);
			$xpath = new DomXPath($dom);
			
			$entry_ids = array();
			
			foreach($xpath->query("//entry") as $entry) {
				$entry_ids[] = $entry->getAttribute('id');
			}
			
			SearchIndex::indexEntry($entry_ids, $ds->dsSource, FALSE);
			
			header('Content-type: text/xml');
			echo $xml;
			exit;

		}
	}