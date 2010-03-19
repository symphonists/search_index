<?php
	
	require_once(TOOLKIT . '/class.gateway.php');
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	
	class contentExtensionSearch_IndexIndexes extends AdministrationPage {
		protected $_driver = null;
		protected $_errors = array();
		
		public function __construct(&$parent){
			parent::__construct($parent);
			
			$this->_uri = URL . '/symphony/extension/search_index';
			$this->_driver = $this->_Parent->ExtensionManager->create('search_index');
			
			$sectionManager = new SectionManager($this->_Parent);			
			$this->_sections = $sectionManager->fetch(NULL, 'ASC', 'name');
			
			$this->_indexes = $this->_driver->getIndexes();
			
			$this->_section = null;
			$this->_index = null;
		}
		
		public function build($context) {
			$this->__prepareEdit($context);		
			parent::build($context);
		}
		
		private function __setContext($section_id) {
			$this->_index = $this->_indexes[$section_id];
			foreach($this->_sections as $s) {
				if ($s->get('id') == $section_id) $this->_section = $s;
			}
		}
				
		public function __prepareEdit($context) {
			$this->__setContext($context[1]);			
			
			if (!is_array($this->_index['fields'])) $this->_index['fields'] = array($this->_index['fields']);
			if (!is_array($this->_index['filters'])) $this->_index['filters'] = array($this->_index['filters']);
		}
		
		public function __actionIndex() {
			$checked = @array_keys($_POST['items']);
			
			if (is_array($checked) and !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete':
						foreach ($checked as $section_id) {
							// set context for this section
							$this->__setContext($section_id);
							// ignore if not index is set for a selected section
							if (is_null($this->_index)) continue;
							
							$this->_driver->deleteEntriesBySection($this->_section);
							unset($this->_indexes[$section_id]);
							$this->_driver->setIndexes($this->_indexes);
						}						
						redirect("{$this->_uri}/indexes/");
						break;
						
					case 're-index':
						
						foreach ($checked as $section_id) {							
							// set context for this section
							// ignore if not index is set for a selected section
							$this->__setContext($section_id);							
							if (is_null($this->_index)) continue;							
							$this->_driver->rebuildIndex($this->_section);
						}
						
						redirect("{$this->_uri}/indexes/");
						break;
				}
			}
		}
		
		public function __actionEdit() {
			
			$fields = $_POST['fields'];
			
			$is_new = !isset($this->_indexes[$this->_section->get('id')]);			
			
			$this->_indexes[$this->_section->get('id')]['fields'] = $fields['included_elements'];
			
			if (!is_array($fields['filter'])) $fields['filter'] = array($fields['filter']);
			
			$filters = array();
			foreach($fields['filter'] as $filter) {
				if (is_null($filter)) continue;
				$filters[key($filter)] = $filter[key($filter)];
			}
			$this->_indexes[$this->_section->get('id')]['filters'] = $filters;
			
			$this->_driver->setIndexes($this->_indexes);
			
			// kick-start index when creating a new index
			if (!$is_new) $this->_driver->rebuildIndex($this->_section);
			
			redirect("{$this->_uri}/indexes/");
		}
		
		public function __viewEdit() {
			$this->addStylesheetToHead(URL . '/extensions/search_index/assets/search_index.css', 'screen', 100);
			$this->addScriptToHead(URL . '/extensions/search_index/assets/search_index.js', 101);
			
			$this->setPageType('form');
			$this->setTitle(__('Symphony') . ' &ndash; ' . __('Search Indexes') . ' &ndash; ' . $this->_section->get('name'));
			$this->appendSubheading("<a href=\"{$this->_uri}/indexes/\">" . __('Search Indexes') . "</a> &raquo; " . $this->_section->get('name'));
			
			$fields = array('fields' => $this->_section->fetchFields(), 'section' => $this->_section);
			
			//var_dump($this->_index);die;
			
			$fields_options = array();
			foreach($fields['fields'] as $f) {				
				$fields_options[] = array(
					$f->get('element_name'),
					in_array($f->get('element_name'), $this->_index['fields']),
					$f->get('label')
				);
			}
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Included Fields')));
			$p = new XMLElement('p', __('Only selected fields will be indexed.'));
			$p->setAttribute('class', 'help');
			$fieldset->appendChild($p);
			
			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');
			
			$label = Widget::Label(__('Included Fields'));
			$label->appendChild(Widget::Select(
				'fields[included_elements][]',
				$fields_options,
				array('multiple'=>'multiple')
			));

			$group->appendChild($label);
			$fieldset->appendChild($group);
			$this->Form->appendChild($fieldset);
			
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings contextual ' . __('sections') . ' ' . __('authors') . ' ' . __('navigation') . ' ' . __('Sections') . ' ' . __('System'));
			$fieldset->appendChild(new XMLElement('legend', __('Index Filters')));
			$p = new XMLElement('p', __('Only entries that pass these filters will be indexed.'));
			$p->setAttribute('class', 'help');
			$fieldset->appendChild($p);

				
			$div = new XMLElement('div');
			$div->setAttribute('class', 'contextual ' . $fields['section']->get('id'));
			$h3 = new XMLElement('h3', __('Filter %s by', array($fields['section']->get('name'))));
			$h3->setAttribute('class', 'label');
			$div->appendChild($h3);
			
			$ol = new XMLElement('ol');
			$ol->setAttribute('class', 'filters-duplicator');
			
			
			if(isset($this->_index['filters']['id'])){
				$li = new XMLElement('li');
				$li->setAttribute('class', 'unique');
				$li->appendChild(new XMLElement('h4', __('System ID')));
				$label = Widget::Label(__('Value'));
				$label->appendChild(Widget::Input('fields[filter]['.$fields['section']->get('id').'][id]', General::sanitize($this->_index['filters']['id'])));
				$li->appendChild($label);
				$ol->appendChild($li);				
			}
			
			$li = new XMLElement('li');
			$li->setAttribute('class', 'unique template');
			$li->appendChild(new XMLElement('h4', __('System ID')));
			$label = Widget::Label(__('Value'));
			$label->appendChild(Widget::Input('fields[filter]['.$fields['section']->get('id').'][id]'));
			$li->appendChild($label);
			$ol->appendChild($li);
			
			if(is_array($fields['fields']) && !empty($fields['fields'])){
				foreach($fields['fields'] as $input){
				
					if(!$input->canFilter()) continue;
							
					if(isset($this->_index['filters'][$input->get('id')])){
						$wrapper = new XMLElement('li');
						$wrapper->setAttribute('class', 'unique');
						$input->displayDatasourceFilterPanel($wrapper, $this->_index['filters'][$input->get('id')], $this->_errors[$input->get('id')], $fields['section']->get('id'));
						$ol->appendChild($wrapper);					
					}
			
					$wrapper = new XMLElement('li');
					$wrapper->setAttribute('class', 'unique template');
					$input->displayDatasourceFilterPanel($wrapper, NULL, NULL, $fields['section']->get('id'));
					$ol->appendChild($wrapper);

				}
			}
			
			$div->appendChild($ol);
			$fieldset->appendChild($div);
			$this->Form->appendChild($fieldset);
			
			
			
			
			
		// Footer -------------------------------------------------------------
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(
				Widget::Input('action[save]',
					__('Save Changes'),
					'submit', array(
						'accesskey'		=> 's'
					)
				)
			);
						
			$this->Form->appendChild($div);
		}
		
		public function __viewIndex() {
			$this->setPageType('table');
			$this->setTitle(__('Symphony') . ' &ndash; ' . __('Search Indexes'));
			
			$this->appendSubheading(__('Search Indexes'));
			
			$tableHead = array();
			$tableBody = array();
			
			$tableHead[] = array('Section', 'col');
			$tableHead[] = array('Indexed Entries', 'col');
			
			if (!is_array($this->_sections) or empty($this->_sections)) {
				$tableBody = array(
					Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', null, count($tableHead))))
				);
			}
			
			else {
				foreach ($this->_sections as $section) {
					
					$col_name = Widget::TableData(
						Widget::Anchor(
							$section->get('name'),
							"{$this->_uri}/indexes/edit/{$section->get('id')}/"
						)
					);
					$col_name->appendChild(Widget::Input("items[{$section->get('id')}]", null, 'checkbox'));
					
					if (isset($this->_indexes[$section->get('id')])) {
						$count = Symphony::Database()->fetchCol(
							'count',
							sprintf(
								"SELECT COUNT(entry_id) as `count` FROM tbl_search_index WHERE `section_id`='%d'",
								$section->get('id')
							)
						);
						$col_count = Widget::TableData($count[0] . ' ' . (((int)$count[0] == 1) ? __('entry') : __('entries')));
					} else {
						$col_count = Widget::TableData('No index', 'inactive');
					}
					
					$tableBody[] = Widget::TableRow(array($col_name, $col_count), null);

				}
			}
			
			$table = Widget::Table(
				Widget::TableHead($tableHead), null, 
				Widget::TableBody($tableBody)
			);
			
			$this->Form->appendChild($table);
			
			$actions = new XMLElement('div');
			$actions->setAttribute('class', 'actions');
			
			$options = array(
				array(null, false, 'With Selected...'),
				array('delete', false, 'Delete index'),
				array('re-index', false, 'Re-index')
			);
			
			$actions->appendChild(Widget::Select('with-selected', $options));
			$actions->appendChild(Widget::Input('action[apply]', 'Apply', 'submit'));
			
			$this->Form->appendChild($actions);

		}
	}