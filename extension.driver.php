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
				'version'		=> '0.9.1',
				'release-date'	=> '2011-07-08',
				'author'		=> array(
					'name'			=> 'Nick Dunn'
				),
				'description' => 'Index text content of entries for efficient fulltext search.'
			);
		}
		
		private function createTables() {
			
			try {
				
				Symphony::Database()->query(
				  "CREATE TABLE IF NOT EXISTS `tbl_fields_search_index` (
					  `id` int(11) unsigned NOT NULL auto_increment,
					  `field_id` int(11) unsigned NOT NULL,
				  PRIMARY KEY  (`id`),
				  KEY `field_id` (`field_id`))");
				
				Symphony::Database()->query(
					"CREATE TABLE IF NOT EXISTS `tbl_search_index` (
					  `id` int(11) NOT NULL auto_increment,
					  `entry_id` int(11) NOT NULL,
					  `section_id` int(11) NOT NULL,
					  `data` text,
					  PRIMARY KEY (`id`),
					  KEY `entry_id` (`entry_id`),
					  FULLTEXT KEY `data` (`data`)
					) ENGINE=MyISAM DEFAULT CHARSET=utf8"
				);
				
				Symphony::Database()->query(
					"CREATE TABLE IF NOT EXISTS `tbl_search_index_logs` (
					  `id` int(11) NOT NULL auto_increment,
					  `date` datetime NOT NULL,
					  `keywords` varchar(255) default NULL,
					  `keywords_manipulated` varchar(255) default NULL,				  
					  `sections` varchar(255) default NULL,
					  `page` int(11) NOT NULL,
					  `results` int(11) default NULL,
					  `session_id` varchar(255) default NULL,
					  PRIMARY KEY  (`id`),
					  FULLTEXT KEY `keywords` (`keywords`)
					) ENGINE=MyISAM DEFAULT CHARSET=utf8;"
				);
				
				Symphony::Database()->query(
					"CREATE TABLE IF NOT EXISTS `tbl_search_index_keywords` (
					  `id` int(11) NOT NULL auto_increment,
					  `keyword` varchar(255) default NULL,
					  PRIMARY KEY  (`id`),
					  FULLTEXT KEY `keyword` (`keyword`)
					) ENGINE=MyISAM DEFAULT CHARSET=utf8;"
				);
				
				Symphony::Database()->query(
					"CREATE TABLE IF NOT EXISTS `tbl_search_index_entry_keywords` (
					  `entry_id` int(11) default NULL,
					  `keyword_id` int(11) default NULL,
					  `frequency` int(11) default NULL,
					  KEY `entry_id` (`entry_id`),
					  KEY `keyword_id` (`keyword_id`)
					) ENGINE=MyISAM DEFAULT CHARSET=utf8;"
				);
				
			}
			catch (Exception $e){
				#var_dump($e);die;
				return FALSE;
			}
			
			return TRUE;
			
		}
		
		private function setInitialConfig() {
			
			Symphony::Configuration()->set('re-index-per-page', 20, 'search_index');
			Symphony::Configuration()->set('re-index-refresh-rate', 0.5, 'search_index');
			
			// names of GET parameters used for custom search DS
			Symphony::Configuration()->set('get-param-prefix', '', 'search_index');
			Symphony::Configuration()->set('get-param-keywords', 'keywords', 'search_index');
			Symphony::Configuration()->set('get-param-per-page', 'per-page', 'search_index');
			Symphony::Configuration()->set('get-param-sort', 'sort', 'search_index');
			Symphony::Configuration()->set('get-param-direction', 'direction', 'search_index');
			Symphony::Configuration()->set('get-param-sections', 'sections', 'search_index');
			Symphony::Configuration()->set('get-param-page', 'page', 'search_index');
			
			// default search params, used if not specifed in GET
			Symphony::Configuration()->set('default-sections', '', 'search_index');
			Symphony::Configuration()->set('default-per-page', 20, 'search_index');
			Symphony::Configuration()->set('default-sort', 'score', 'search_index');
			Symphony::Configuration()->set('default-direction', 'desc', 'search_index');
			
			Symphony::Configuration()->set('excerpt-length', 250, 'search_index');
			Symphony::Configuration()->set('min-word-length', 3, 'search_index');
			Symphony::Configuration()->set('max-word-length', 30, 'search_index');
			Symphony::Configuration()->set('stem-words', 'yes', 'search_index');
			Symphony::Configuration()->set('build-entries', 'no', 'search_index');
			Symphony::Configuration()->set('mode', 'like', 'search_index');
			Symphony::Configuration()->set('log-keywords', 'yes', 'search_index');
						
			Administration::instance()->saveConfig();
			
		}

		/**
		* Set up configuration defaults and database tables
		*/		
		public function install(){
			
			$this->createTables();
			$this->setInitialConfig();
			
			return TRUE;
		}
		
		public function update($previousVersion){
			
			if(version_compare($previousVersion, '0.6', '<')){
				Symphony::Database()->query("ALTER TABLE `tbl_search_index_logs` ADD `keywords_manipulated` varchar(255) default NULL");
			}
			
			// lower versions get the full upgrade treatment, new tables and config
			// should retain "indexes" and "synonyms" in config though.
			if(version_compare($previousVersion, '0.7.1', '<')){
				$this->install();
			}
			
			return TRUE;
		}

		/**
		* Cleanup after yourself, remove configuration and database tables
		*/
		public function uninstall(){
			
			Symphony::Configuration()->remove('search_index');			
			Administration::instance()->saveConfig();
			
			try{
				Symphony::Database()->query("DROP TABLE `tbl_search_index`");
				Symphony::Database()->query("DROP TABLE `tbl_fields_search_index`");
				Symphony::Database()->query("DROP TABLE `tbl_search_index_logs`");
				Symphony::Database()->query("DROP TABLE `tbl_search_index_keywords`");
				Symphony::Database()->query("DROP TABLE `tbl_search_index_entry_keywords`");
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
				array(
					'page' => '/frontend/',
					'delegate' => 'EventPostSaveFilter',
					'callback' => 'indexEntry'
				),
				// Dashboard
				array(
					'page'		=> '/backend/',
					'delegate'	=> 'DashboardPanelRender',
					'callback'	=> 'renderPanel'
				),
				array(
					'page'		=> '/backend/',
					'delegate'	=> 'DashboardPanelTypes',
					'callback'	=> 'dashboardPanelTypes'
				),
			);
		}
		
		/**
		* Append navigation to Blueprints menu
		*/
		public function fetchNavigation() {
			return array(
				array(
					'location'	=> __('Search Index'),
					'name'		=> __('Indexes'),
					'link'		=> '/indexes/'
				),
				array(
					'location'	=> __('Search Index'),
					'name'		=> __('Synonyms'),
					'link'		=> '/synonyms/'
				),
				array(
					'location'	=> __('Search Index'),
					'name'		=> __('Logs'),
					'link'		=> '/logs/'
				),
			);
		}
		
		/**
		* Index this entry for search
		*
		* @param object $context
		*/
		public function indexEntry($context) {
			SearchIndex::indexEntry($context['entry']->get('id'), $context['entry']->get('section_id'));
		}
		
		/**
		* Delete this entry's search index
		*
		* @param object $context
		*/
		public function deleteEntryIndex($context) {
			if (is_array($context['entry_id'])) {
				foreach($context['entry_id'] as $entry_id) {
					SearchIndex::deleteIndexByEntry($entry_id);
				}
			} else {
				SearchIndex::deleteIndexByEntry($context['entry_id']);
			}
		}
		
		/*-------------------------------------------------------------------------
			Dashboard
		-------------------------------------------------------------------------*/
		
		public function dashboardPanelTypes($context) {
			$context['types']['search_index'] = "Search Index";
		}

		public function renderPanel($context) {
			$config = $context['config'];

			switch($context['type']) {
				case 'search_index':

					$logs = SearchIndex::getLogs('date', 'desc', 1);

					$thead = array(
						array(__('Date'), 'col'),
						array(__('Keywords'), 'col'),
						array(__('Results'), 'col')
					);
					$tbody = array();

					if (!is_array($logs) or empty($logs)) {
						$tbody = array(Widget::TableRow(array(
							Widget::TableData(
								__('No data available.'),
								'inactive',
								null,
								count($thead)
							)))
						);
					}
					
					else {

						foreach ($logs as $log) {
							$tbody[] = Widget::TableRow(
								array(
									Widget::TableData(DateTimeObj::get(__SYM_DATETIME_FORMAT__, strtotime($log['date']))),
									Widget::TableData($log['keywords']),
									Widget::TableData($log['results'])
								)
							);
						}
					}

					$table = Widget::Table(
						Widget::TableHead($thead), null,
						Widget::TableBody($tbody), null
					);
					$table->setAttribute('class', 'skinny');

					$context['panel']->appendChild($table);
					$context['panel']->appendChild(new XMLElement('p', '<a href="'.(URL . '/symphony/extension/search_index/logs/').'">' . __('View full search logs') . ' &#8594;</a>', array('style' => 'margin:0.7em;text-align:right;')));
					
				break;

			}
			
		}
		
		
	}
	