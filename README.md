Bolt CMS Elasticsearch Extension
======================
A simple extension that integrates with the official `elasticsearch` package. Documentation for the plugin can be found 
under https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/index.html . Please note that you will need to 
manually install `elasticsearch` into your root Bolt directory. Currently, it only does the following 
things: 

- Connects to an Elasticsearch instance and creates the index, index settings, and mappings
- Imports existing data into the Elasticsearch instance
- Listens to create, update, and delete events to content types and updates the Elasticsearch instance appropriately 

It does NOT
- Integrate search functionality in Bolt
- Include any twig functions to use in your Bolt CMS to help search through Elasticsearch
- Adjust once the content types are changed

## Installation
Installation is relatively simple, but since it needs the `elasticsearch-php` package, it will need to be installed separately. 
This can be done by logging into your server through SSH and installing the library through command line. 

`cd <bolt directory>`

**NOTE** - Be sure to install the correct version of the library for your Elasticsearch version. A list of appropriate versions 
can be found here - https://github.com/elastic/elasticsearch-php#version-matrix
You will need to replace `"~6.0"` with the correct version of the library, whether it be `"~5.0"`, `"~2.0"`, or etc...

`composer require elasticsearch/elasticsearch "~6.0"` 

After that is done, then install this extension through the Bolt GUI.

Finally, setup the index, mappings, and import the content into Elasticsearch. A new page will be added to the backend in 
the menu called `ES Status`. It will allow you to create the index and import data.

*If you modify the contenttypes.yml file after you create the index and import data, then you will need re-index your 
data and re-create the index to include the new content types or fields.*

## Configuration
A new configuration file will be created in `app/config/extensions/elastic.kemper.yml` once it is installed 
that allows you to customize most things.

## Making Content Types Searchable
You can make a content type searchable by setting `searchable: true` in the `app/config/contenttypes.yml` file. They will 
not be included in the index, if that value is not included with the content type.

### Hosts
You can define one or many hosts for your elasticsearch instance under hosts. More information can be found here - 
https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/_configuration.html

### Index Name
The index name is the name of the index, enough said.

### Index Settings
The index settings will include any settings that you might want to include for the index. You can find more information 
on configuring this through the Elasticsearch documentation - https://www.elastic.co/guide/en/elasticsearch/guide/current/_index_settings.html

### Index Mappings
The index mappings can be defined for individual fields if needed. Currently, all fields are indexed per content type. 
This allows you to add mapping information for fields other than what Elasticsearch gives each field by default. Datetime 
fields are automatically defaulted to datetime `YYYY-MM-dd HH:mm:ss`. The other fields are guessed by Elasticsearch.

### Usage
After everything is installed, it should be working properly in the background. Every time a content type is created, updated, 
and deleted it will log it in the system log. 

##TODO
 - [ ] - Integrate search functionality into Bolt (**Maybe this should be in another extension to prevent a monolithic extension**)
    - [ ] - Create a search route to replace the existing search route that uses Elasticsearch
    - [ ] - Create a TWIG function similar to `search` that integrates into Elasticsearch
 - [ ] - Once the contenttypes.yml changes update Elasticsearch appropriately
 - [ ] - Add tests