# 1.0.1

This is our first maintenance release that solves several important
issues.

* Added support for automated model reloading after save(). 
* Added documentation sections for Advanced Topics with tips and tricks
  * how to implement [soft-delete](http://agile-data.readthedocs.io/en/develop/advanced.html#soft-delete), [audit](http://agile-data.readthedocs.io/en/develop/advanced.html#audit-fields)
  * how to [override default method actions](http://agile-data.readthedocs.io/en/develop/model.html?highlight=hook#how-to-prevent-action), e.g. [delete()](shttp://agile-data.readthedocs.io/en/develop/advanced.html#soft-delete-that-overrides-default-delete)
  * how to [verify updates](http://agile-data.readthedocs.io/en/develop/model.html?highlight=hook#how-to-verify-updates)
  * how to create [fields with unique values](http://agile-data.readthedocs.io/en/develop/advanced.html#creating-unique-field)

* Updated to latest version in dependencies

# 1.0.0

This is our first stable version release for Agile Data. The class and
method structure has sufficiently matured and will not be changed much
anymore. Further 1.0.x versions will be focused on increasing stability
and bugfixes. Versions 1.x will add more notable features, but if any
incompatibilities will occur, then they will be mentioned in release
notes and CHAGELOG.md

* Rewrote QuickStart guide and README.md, so everyone should re-read them
* added Model::setLimit, setOrder
* added Model::export([])
* added Model iterator
* added Model::each
* added concise var_dump() support for most objects
* added Model::getRef() and Model::getRefs()
* added Field_SQL_One::addFields() and addTitle()
* added support for nested joins in model
* added Model::withID
* added Model::loadAny()
* added Model::loadBy() and tryLoadBy()
* added Model::rawIterator()
* field consistency improvement in action('select') and action('fx')
* Model::ref() added 2nd argument as model defaults
* defaults with value of NULL are ignored
* improved coding style with StyleCI

## 0.5.1 Minor Cleanups

* renamed aciton('fieldValues') into action('field');
* added support for `title_field`
* minor bugfixes and cleanups
* added more documentation

## 0.5 Conditions, Relations, Expressions, Cleanups

With the foundation for Agile Data complete, new features are being
developed quickly. This version brings out 3 major features:

* [Conditions](http://agile-data.readthedocs.io/en/latest/conditions.html)
* [Relations](http://agile-data.readthedocs.io/en/latest/relations.html)
* [Expressions](http://agile-data.readthedocs.io/en/latest/expressions.html)

Agile Data is now being used in production projects, which means we
start getting all sorts of fixes in. This release aims at cleaning
thigs up and fixing issues that were not triggered by the unit-tests.

* added more actions, count, fx, field
* added Model::tryLoadAny()

## 0.4 Table Joins

* [Table Joins](http://agile-data.readthedocs.io/en/latest/joins.html)
* added hook beforeModify, afterModify
* added Model::delete()
* added hook beforeDeleteQuery, initSelectQuery, beforeUpdateQuery.
* added some practical tests (smbo)


## 0.3

* added Model::addFields() that can add multiple fields
* added Model::tryLoad()
* added Model::insert()
* added Persistence::connect() as a factory method
* implemeted model-related hooks
* You can now var_dump(field) safely
* specify optional table to persistence layer's method (array)
* integrate colorful exception into test-suite
* added setDB() getDB() wrappers for managing db state in test-suite
* added class Structure as a local extension for DSQL, for creating tables

## 0.2

* Active Record (array access) implementation
* Driver implementation Array and SQL (on top of DSQL)
* Record field type bi-directional mapping (boolean, date, enum, json)
* Mechanic for tracking field changes (original values, dirty etc)
* Ability to move field from one persistence driver to another


## 0.1

* Initial Release
* Bootstraped Documentation (sphinx-doc)
* Implemented CI
