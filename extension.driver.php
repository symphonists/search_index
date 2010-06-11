<?php

	require_once(TOOLKIT . '/class.datasource.php');
	require_once(EXTENSIONS . '/search_index/lib/class.search_index.php');
	require_once(EXTENSIONS . '/search_index/lib/class.entry_xml_datasource.php');
	require_once(EXTENSIONS . '/search_index/lib/class.reindex_datasource.php');
	
	class Extension_Search_Index extends Extension {
		
		/**
		* Extension meta data
		*/
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

		/**
		* Set up configuration defaults and database tables
		*/		
		public function install(){
			
			// number of entries per page when rebuilding index
			Symphony::Configuration()->set('re-index-per-page', 20, 'search_index');
			// refresh frequency when rebuilding index
			Symphony::Configuration()->set('re-index-refresh-rate', 0.5, 'search_index');
			
			// append wildcard * to the end of search phrases (reduces performance, increases matches)
			Symphony::Configuration()->set('append-wildcard', 'yes', 'search_index');
			
			// names of GET parameters used for custom search DS
			Symphony::Configuration()->set('get-param-prefix', '', 'search_index');
			Symphony::Configuration()->set('get-param-keywords', 'keywords', 'search_index');
			Symphony::Configuration()->set('get-param-per-page', 'per-page', 'search_index');
			Symphony::Configuration()->set('get-param-sort', 'sort', 'search_index');
			Symphony::Configuration()->set('get-param-direction', 'direction', 'search_index');
			Symphony::Configuration()->set('get-param-sections', 'sections', 'search_index');
			Symphony::Configuration()->set('get-param-page', 'page', 'search_index');
			
			Administration::instance()->saveConfig();
			
			try {
				
				Symphony::Database()->query(
				  "CREATE TABLE IF NOT EXISTS `tbl_fields_search_index` (
					  `id` int(11) unsigned NOT NULL auto_increment,
					  `field_id` int(11) unsigned NOT NULL,
				  PRIMARY KEY  (`id`),
				  KEY `field_id` (`field_id`))");
				
				Symphony::Database()->query(
					"CREATE TABLE `tbl_search_index` (
					  `id` int(11) NOT NULL auto_increment,
					  `entry_id` int(11) NOT NULL,
					  `section_id` int(11) NOT NULL,
					  `data` text,
					  PRIMARY KEY (`id`),
					  FULLTEXT KEY `data` (`data`)
					) ENGINE=MyISAM DEFAULT CHARSET=utf8"
				);
				
			}
			catch (Exception $e){
				return false;
			}
			
			return true;
		}

		/**
		* Cleanup after yourself, remove configuration and database tables
		*/
		public function uninstall(){
			
			Symphony::Configuration()->remove('search_index');			
			$this->_Parent->saveConfig();
			
			try{
				Symphony::Database()->query("DROP TABLE `tbl_search_index`");
				Symphony::Database()->query("DROP TABLE `tbl_fields_search_index`");
			}
			catch(Exception $e){
				return false;
			}
			return true;
		}
		
		/**
		* Callback functions for backend delegates
		*/		
		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/publish/new/',
					'delegate'	=> 'EntryPostCreate',
					'callback'	=> 'indexEntry'
				),				
				array(
					'page'		=> '/publish/edit/',
					'delegate'	=> 'EntryPostEdit',
					'callback'	=> 'indexEntry'
				),
				array(
					'page'		=> '/publish/',
					'delegate'	=> 'Delete',
					'callback'	=> 'deleteEntryIndex'
				),
			);
		}
		
		/**
		* Append navigation to Blueprints menu
		*/
		public function fetchNavigation() {
			return array(
				array(
					'location'	=> 'Blueprints',
					'name'		=> 'Search Indexes',
					'link'		=> '/indexes/'
				),
			);
		}
		
		/**
		* Index this entry for search
		*
		* @param object $context
		*/
		public function indexEntry($context) {
			SearchIndex::indexEntry($context['entry']->get('id'), $context['section']->get('id'));			
		}
		
		/**
		* Delete this entry's search index
		*
		* @param object $context
		*/
		public function deleteEntryIndex($context) {
			SearchIndex::deleteIndexByEntry($context['entry_id']);
		}
		
	}
	