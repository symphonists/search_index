# Search Index

* Version: 0.6.5
* Author: [Nick Dunn](http://nick-dunn.co.uk)
* Build Date: 2011-02-17
* Requirements: Symphony 2.2

## Description
Search Index provides an easy way to implement high performance fulltext searching on your Symphony site. By setting filters for each Section in your site you control which entries are indexed and therefore searchable. Frontend search can be implemented either using the Search Index Field that allows keyword filtering in data sources, or the included Search Index data source for searching multiple sections at once.

## Usage
1. Add the `search_index` folder to your Extensions directory
2. Enable the extension from the Extensions page
3. Configure indexes from Search Index > Indexes

### Configuring section indexes
After installation navigate to Search Index > Indexes whereupon you will see a list of all sections in your site. Click on a name to configure the indexing criteria for that section. The index editor works the same as the data source editor:

* Only values of fields selected from the **Included Elements** list are used for searching
* **Index Filters** work exactly like data source filters. Use these to ensure only desired entries are indexed

Once saved the "Index" column will display "0 entries" on the Search Indexes page. Select the row and choose "Re-index Entries" from the With Selected menu. When the page reloads you will see the index being rebuilt, one page of results at a time.

Multiple sections can be selected at once for re-indexing.

The page size and speed of refresh can be modified by editing the `re-index-per-page` and `re-index-refresh-rate` variables in your Symphony `config.php`.

### Fulltext search in a data source (single section)
Adding a keyword search to an existing data source is extremely easy. Start by adding the Search Index Filter field to your section. This allows you to add a filter on this field when building a data source. For example:

* add the Search Index to your section
* modify your data source to filter this field with a filter value of `{$url-keywords}`
* attach the data source to a page and access like `/my-page/?keywords=foo+bar`

The filtered entries returned will only be those that contain the word "foo" in their text index.

### Fulltext search across multiple Sections
A full-site search can be achieved using the custom Search Index data source included with this extension. Attach this data source to a page and invoke it using the following GET parameters:

* `keywords` the string to search on e.g. `foo bar`
* `sort` either `id` (entry ID), `date` (entry creation date), `score` (relevance) or `score-recency` (relevance with a higher weighting for newer entries) (defaults to `score`)
* `direction` either `asc` or `desc` (defaults to `desc`)
* `per-page` number of results per page (defaults to `20`)
* `page` the results page number
* `sections` a comma-delimited list of section handles to search within (only those with indexes will work) e.g. `articles,comments`

Your search form might look like this:

	<form action="/search/" method="get">
		<label>Search <input type="text" name="keywords" /></label>
		<input type="hidden" name="sort" value="score-recency" />
		<input type="hidden" name="per-page" value="10" />
		<input type="hidden" name="sections" value="articles,comments,categories" />
	</form>

If you want to change the names of these variables, they can be modified in your Symphony `config.php`. If you are using Form Controls to post these variables from a form your variable names may be in the form `fields[...]`. If so, add `fields` to the `get-param-prefix` variable in your Symphony `config.php`.

#### Using Symphony URL Parameters
The default is to use GET parameters such as `/search/?keywords=foo+bar&page=2` but if you prefer to use URL Parameters such as `/search/foo+bar/2/`, set the `get-param-prefix` variable to `param_pool` in your `config.php` and the extension will look at the Param Pool rather than the $_GET array for its values.

#### Example XML

The XML returned from this data source looks like this:

	<search keywords="foo+bar" sort="score" direction="desc">
		<pagination total-entries="5" total-pages="1" entries-per-page="20" current-page="1" />
		<sections>
			<section id="1" handle="articles">Articles</section>
			<section id="2" handle="comments">Comments</section>
		</sections>
		<entry id="3" section="comments" />
		<entry id="5" section="articles" />
		<entry id="2" section="articles" />
		<entry id="1" section="comments" />
		<entry id="3" section="comments" />
	</search>

This in itself is not enough to render a results page. To do so, use the `$ds-search` Output Parameter created by this data source to filter by System ID in other data sources. In the example above you would create a new data source each for Articles and Comments, filtering System ID by the `$ds-search` parameter. Use XSLT to iterate over the `<entry ... />` elements above, and cross-reference with the matching entries from the Articles and Comments data sources.

## Weighting
We all know that all sections are equal, only some are more equal than others ;-) You can give higher or lower weighting to results from certain sections, by issuing them a weighting when you configure their Search Index. The default is `Medium` (no weighting), but if you want more chance of entries from your section appearing higher up the search results, choose `High`; or for even more prominence `Highest`. The opposite is true: to bury entries lower down the results then choose `Low` or `Lowest`. This weighting has the effect of doubling/quadrupling or halving/quartering the original "relevance" score calculated by the search.

## Boolean search
Search Index makes use of MySQL's boolean fulltext search. When using the multi-section search, you can use the `+` and `-` directives to make results more specific. In fact, Search Index actually appends `+` to all words, making them required, to make the results as specific is possible. You can also use double-quotes too, to search for specific strings.

For more information see <http://dev.mysql.com/doc/refman/5.1/en/fulltext-boolean.html>

## Configuration
The common configuration options are discussed above. This is a full list of the variables you *should* see in your `config.php`. If some are missing it is because you have previously installed an earlier version of the extension. You can add these variables manually to make use of them.

### `re-index-per-page`
Defaults to `20`. When manually re-indexing sections in the backend (Search Index > Indexes, highlight rows an select "Re-index" from the With Selected dropdown) this is the number of entries per "page" that will be re-indexed at once. If you have 100 entries and `re-index-per-page` is `20` then you will have 5 pages of entries that will index, one after the other.

### `re-index-refresh-rate`
Defaults to `0.5` seconds. This is the "pause" between each cycle of indexing when manually re-indexing sections. If you have a high traffic site (or slow server) and you are worried that many consecutive page refreshes will use too much server power, then choose a higher number and there will be a longer pause between each page of indexing. The larger the number, the longer you have to wait during re-indexing. Set to `0` for super-quick times.

### `append-wildcard`
Defaults to `no`. When enabled this option will append the `*` wildcard character to the end of each word in your search phrase thereby allowing partial word matches. For example:

    before: foo bar
	after: foo* bar*

If you have entries containing the text "food" or "barn" the first search would not find these. The second would.

This is disabled by default because it will decrease the relevance of your results and may not perform well on massive datasets. If you need this functionality you should investigate "word stemming" instead.

### `append-all-words-required`
Defaults to `yes`. When enabled this option will append the `+` "required" character to the start of each word in your search phrase. This has the effect of making all words required. For example:

    before: foo bar
	after: +foo +bar

If you have one entry containing "foo" and another containing "bar" (but neither contain both words), then the first search will find both entries, and the second search will find none. 

While this may decrease the number of results, the results will be more specific and hopefully relevant.

### TODO: document these!

* `default-sections` => NULL,
* `excerpt-length` => `250`,
* `get-param-prefix` => NULL,
* `get-param-keywords` => `keywords`,
* `get-param-per-page` => `per-page`,
* `get-param-sort` => `sort`,
* `get-param-direction` => `direction`,
* `get-param-sections` => `sections`,
* `get-param-page` => `page`,
* `indexes` => `a:1:{i:1;a:3:{s:6:\"fields\";a:2:{i:0;s:5:\"test2\";i:1;s:9:\"html-test\";}s:9:\"weighting\";s:1:\"2\";s:7:\"filters\";a:0:{}}}`,

## Synonyms

Version 0.6 introduced the concept of synonyms, available by choosing Search Index > Synonyms. This allows you to configure word replacements so that commonly mis-spelt terms are automatically fixed, or terms with many alternative spellings or variations can be normalised to a single spelling. An example:

* Replacement word `United Kingdom`
* Synonyms: `uk, great britain, GB, united kingdoms`

When a user searches for any of the synonym words, they will be replaced by the replacement word. So if a user searches for `countries in the UK` their search will actually use the phrase `counties in the United Kingdom`. 

Synonym matches are case-insensitive.

## Log viewer

You can see what your users have searched for on the Search Index > Logs page. This lists every unique search. A "unique search" is a combination of session and keyword, so if a user searches for the same keyword three times during one session, all three will be logged, but only displayed once in the Logs table.

Column descriptions:

* `Date` is the time of the search. If a user has searched multiple times, this is the time of the _first_search
* `Keywords` is the raw keyword phrase the user used
* `Adjusted Keywords` shows the keyword phrase if it was modified by synonym expansion
* `Results` is the number of matched entries the search yielded
* `Depth` is the maximum number of search results pages the user clicked through

## Known issues
* you can not order results by relevance score when using a single data source. This is only available when using the custom Search Index data source

## Changelog

### 0.6.2, 0.6.3
* added sortable/searchable Logs page to allow viewing of searches

### 0.6.1
* tidied up the user interface

### 0.6
* extension now has its own backend menu rather than sitting under Blueprints
* added support for synonyms, for example a user searching for "ds" could have the term expanded to "data source"

### 0.5
* fixed bug whereby multiple slashes were added to serialised array in `config.php`
* fixed bug whereby an SQL error would occur when all indexes had Medium weighting

### 0.4
* fixed several bugs (thanks designermonkey, Allen, klaftertief, icek, zimmen!)
* added additional indexes to `tbl_search_index` and `tbl_search_index_logs` for mega performance improvement for single-section search, it is recommended you make sure indexes in your tables match these:
  * `tbl_search_index`: `KEY 'entry_id' ('entry_id')` and `FULLTEXT KEY 'data' ('data')`
  * `tbl_search_index_logs`: `FULLTEXT KEY 'keywords' ('keywords')`
* refined excerpt code
* added option to use pretty URL Parameters instead of `$_GET` params only
* added Symphony 2.1.2 readiness

### 0.3a
* massively improved indexing performance (large sections 10s to 1.5s per page of indexing)
* removed auto-wildcard, added boolean support
* added ability to store default sections in config (thanks Jonas!)
* added ability to weight sections up or down (dropdown when modifying the index)
* each search is now logged in a separate table for auditing and analysis (future versions might provide a UI to inspect this data)
* added new sort value `score-recency` which sorts results using a combined function of relevance and recency (entry creation date)
* added summary excerpt of matched text in XML, including highlighting of matched words

### 0.2a
* public release