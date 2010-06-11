# Search Index
Version: 0.2a  
Author: [Nick Dunn](http://nick-dunn.co.uk)  
Build Date: 2010-06-08  
Requirements: Symphony 2.0.8

## Description
Search Index provides an easy way to implement high performance fulltext searching on your Symphony site. By setting filters for each Section in your site you control which entries are indexed and therefore searchable. Frontend search can be implemented either using the Search Index Field that allows keyword filtering in Data Sources, or the included Search Index Data Source for searching multiple sections at once.

## Usage
1. Add the `search_index` folder to your Extensions directory
2. Enable the extension from the Extensions page
3. Configure indexes from Blueprints > Search Indexes

### Configuring section indexes
After installation navigate to Blueprints > Search Indexes whereupon you will see a list of all sections in your site. Click on a name to configure the indexing criteria for that section. The index editor works the same as the Data Source editor:

* Only values of fields selected from the **Included Elements** list are used for searching
* **Index Filters** work exactly like Data Source filters. Use these to ensure only desired entries are indexed

Once saved the "Index" column will display "0 entries" on the Search Indexes page. Select the row and choose "Re-index Entries" from the With Selected menu. When the page reloads you will see the index being rebuilt, one page of results at a time.

Multiple sections can be selected at once for re-indexing.

The page size and speed of refresh can be modified by editing the `re-index-per-page` and `re-index-refresh-rate` variables in your Symphony `config.php`.

### Fulltext search in a Data Source (single section)
Adding a keyword search to an existing Data Source is extremely easy. Start by adding the Search Index field to your section. This allows you to add a filter on this field when building a Data Source. For example:

* add the Search Index to your section
* modify your Data Source to filter this field with a filter value of `{$url-keywords}`
* attach the Data Source to a page and access like `/my-page/?keywords=foo+bar`

The filtered entries returned will only be those that contain the word "foo" in their text index.

### Fulltext search across multiple Sections
A full-site search can be achieved using the custom Search Index Data Source included with this extension. Attach this Data Source to a page and invoke it using the following GET parameters:

* `keywords` the string to search on e.g. `foo bar`
* `sort` either `id` (entry ID), `date` (entry creation date) or `score` (relevance)
* `direction` either `asc` or `desc`
* `per-page` number of results per page (defaults to 20)
* `page` the results page number
* `sections` a comma-delimited list of section handles to search within (only those with indexes will work) e.g. `articles,comments`

If you want to change the names of these variables, they can be modified in your Symphony `config.php`. If you are using Form Controls to post these variables from a form your variable names may be in the form `fields[...]`. If so, add `fields` to the `get-param-prefix` variable in your Symphony `config.php`.

The XML returned from this Data Source looks like this:

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

This in itself is not enough to render a results page. To do so, use the `$ds-search` Output Parameter created by this Data Source to filter by System ID in other Data Sources. In the example above you would create a new Data Source both for Articles and Comments, filtering System ID by the `$ds-search` parameter. Use XSLT to iterate over the `<entry ... />` elements above, and cross-reference with the matching entries from the Articles and Comments Data Sources.

## Known issues
* you can not order results by relevance score when using a single Data Source. This is only available when using the custom Search Index Data Source