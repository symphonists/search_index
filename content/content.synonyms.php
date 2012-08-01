<?php
	
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(EXTENSIONS . '/search_index/lib/class.search_index.php');
	
	class contentExtensionSearch_IndexSynonyms extends AdministrationPage {
		protected $_errors = array();
		
		public function __construct(){
			parent::__construct();
			
			$this->_uri = SYMPHONY_URL . '/extension/search_index';
			
			$this->_synonyms = SearchIndex::getSynonyms();
			$this->_synonym = NULL;
			$this->_hash = NULL;
		}
		
		public function build($context) {
			$this->__prepareEdit($context);		
			parent::build($context);
		}
		
		private function __setContext($hash) {
			$this->_hash = $hash;
			$this->_synonym = $this->_synonyms[$hash];
		}
				
		public function __prepareEdit($context) {
			$this->__setContext($context[1]);
		}
		
		public function __actionIndex() {
			$checked = @array_keys($_POST['items']);
			
			if (is_array($checked) and !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete':
						foreach ($checked as $hash) {							
							$this->__setContext($hash);							
							unset($this->_synonyms[$hash]);
						}
						SearchIndex::saveSynonyms($this->_synonyms);
						redirect("{$this->_uri}/synonyms/");
						break;
				}
			}
		}
		
		public function __actionEdit() {
			
			$synonym = $_POST['synonym'];
			
			// remove existing instance of hash
			if($synonym['hash'] != '') unset($this->_synonyms[$synonym['hash']]);
			
			$this->_synonyms[sha1($synonym['word'])] = array(
				'word' => $synonym['word'],
				'synonyms' => $synonym['synonyms']
			);
			
			SearchIndex::saveSynonyms($this->_synonyms);
			
			redirect("{$this->_uri}/synonyms/");
		}
		
		public function __viewEdit() {
			$this->addStylesheetToHead(URL . '/extensions/search_index/assets/search_index.css', 'screen', 100);
			// $this->addScriptToHead(URL . '/extensions/search_index/assets/search_index.js', 101);

			$this->setPageType('form');
			$this->setTitle(__('Symphony') . ' &ndash; ' . __('Search Indexes'));
			
			$subheading = !empty($this->_synonym['word']) ? $this->_synonym['word'] : __('Untitled');
			$this->appendSubheading($subheading);
			$this->insertBreadcrumbs(array(
				Widget::Anchor(__('Synonyms'), $this->_uri . '/synonyms/'),
			));
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Replacement word')));
			$p = new XMLElement('p', __('Matching synonyms will be replaced with this word.'));
			$p->setAttribute('class', 'help');
			$fieldset->appendChild($p);
						
			$label = Widget::Label(__('Word'));
			$label->appendChild(Widget::Input(
				'synonym[word]',
				$this->_synonym['word']
			));
			$fieldset->appendChild($label);
			$fieldset->appendChild(new XMLElement('p', __('e.g. United Kingdom'), array('class'=>'help')));
			
			$this->Form->appendChild($fieldset);
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Synonyms')));
			$p = new XMLElement('p', __('These words will be replaced with the word above. Separate multiple words with commas.'));
			$p->setAttribute('class', 'help');
			$fieldset->appendChild($p);
						
			$label = Widget::Label(__('Synonyms'));
			$label->appendChild(Widget::Textarea(
				'synonym[synonyms]',
				5, 40,
				$this->_synonym['synonyms']
			));
			$fieldset->appendChild($label);
			$fieldset->appendChild(new XMLElement('p', __('e.g. UK, Great Britain, GB'), array('class'=>'help')));
			
			$this->Form->appendChild(new XMLElement('input', NULL, array('type'=>'hidden','name'=>'synonym[hash]','value'=>$this->_hash)));
			
			$this->Form->appendChild($fieldset);
			
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
			
			$this->appendSubheading(__('Synonyms'),
				Widget::Anchor(__('Create New'), $this->_uri . '/synonyms/edit/', __('Create New Synonym'), 'button create')
			);

			$this->Form->appendChild(new XMLElement('p', __('Configure synonym expansion, so that common misspellings or variations of phrases can be normalised to a single phrase.'), array('class' => 'intro')));
			
			$this->addStylesheetToHead(URL . '/extensions/search_index/assets/search_index.css', 'screen', 100);
			
			$tableHead = array();
			$tableBody = array();
			
			$tableHead[] = array(__('Word'), 'col');
			$tableHead[] = array(__('Synonyms'), 'col');
			
			if (!is_array($this->_synonyms) or empty($this->_synonyms)) {
				$tableBody = array(
					Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', null, count($tableHead))))
				);
			}
			
			else {
				
				foreach ($this->_synonyms as $hash => $synonym) {					
					$col_word = Widget::TableData(
						Widget::Anchor(
							$synonym['word'],
							"{$this->_uri}/synonyms/edit/{$hash}/"
						)
					);
					$col_word->appendChild(Widget::Input("items[{$hash}]", null, 'checkbox'));
					$col_synonyms = Widget::TableData($synonym['synonyms']);
					$tableBody[] = Widget::TableRow(array($col_word, $col_synonyms));
				}
			}
			
			$table = Widget::Table(
				Widget::TableHead($tableHead), null, 
				Widget::TableBody($tableBody),
				'selectable'
			);
			
			$this->Form->appendChild($table);
						
			$actions = new XMLElement('div');
			$actions->setAttribute('class', 'actions');
			
			$options = array(
				array(NULL, FALSE, __('With Selected...')),
				array('delete', FALSE, __('Delete')),
			);
			
			$actions->appendChild(Widget::Apply($options));
			
			$this->Form->appendChild($actions);

		}
	}