# 1.3.0

Add support for Oracle and PostgreSQL, adding support for sequences and migrate to ATK Data 1.2.x

**Fixed bugs:**

- Tests of CSV persistence fail on Windows [\#271](https://github.com/atk4/data/issues/271)

**Closed issues:**

- Problem with oracle insert [\#280](https://github.com/atk4/data/issues/280)
- Oracle dates not working properly [\#279](https://github.com/atk4/data/issues/279)
- After latest composer update, warning is thrown in Persistence\_SQL-\>initQueryFields\(\) [\#274](https://github.com/atk4/data/issues/274)
- \[epic\] Implement support for Oracle [\#270](https://github.com/atk4/data/issues/270)

**Merged pull requests:**

- Model Sequence, lastInserId and other fixes [\#285](https://github.com/atk4/data/pull/285) ([DarkSide666](https://github.com/DarkSide666))
- Feature/dont supply null [\#284](https://github.com/atk4/data/pull/284) ([romaninsh](https://github.com/romaninsh))
- Don't hardcode "id" - use owner model $id\_field property [\#278](https://github.com/atk4/data/pull/278) ([DarkSide666](https://github.com/DarkSide666))
- Make model use AppScopeTrait thus inheriting $app property from the persistence [\#277](https://github.com/atk4/data/pull/277) ([romaninsh](https://github.com/romaninsh))
- Fix initQueryFields \(274\) [\#275](https://github.com/atk4/data/pull/275) ([DarkSide666](https://github.com/DarkSide666))
- Feature/spellcheck [\#273](https://github.com/atk4/data/pull/273) ([DarkSide666](https://github.com/DarkSide666))
- Feature/oracle support [\#272](https://github.com/atk4/data/pull/272) ([DarkSide666](https://github.com/DarkSide666))

## 1.3.1

**Closed issues:**

- Typecasting for id field [\#293](https://github.com/atk4/data/issues/293)
- Should action\('xyz'\)-\>execute\(\) trigger hooks? [\#291](https://github.com/atk4/data/issues/291)

**Merged pull requests:**

- Feature/various improvements [\#297](https://github.com/atk4/data/pull/297) ([romaninsh](https://github.com/romaninsh))
- use ValidationException [\#296](https://github.com/atk4/data/pull/296) ([DarkSide666](https://github.com/DarkSide666))
- works better with namespaces [\#295](https://github.com/atk4/data/pull/295) ([romaninsh](https://github.com/romaninsh))
- works better with namespaces [\#294](https://github.com/atk4/data/pull/294) ([romaninsh](https://github.com/romaninsh))
- Travis db testing [\#292](https://github.com/atk4/data/pull/292) ([gartner](https://github.com/gartner))
- Although we have added PostgreSQL, persistence does not recognize it [\#287](https://github.com/atk4/data/pull/287) ([romaninsh](https://github.com/romaninsh))
- Allows static persistence to use empty array [\#286](https://github.com/atk4/data/pull/286) ([romaninsh](https://github.com/romaninsh))

## 1.3.2

**Implemented enhancements:**

- add $caption, getModelCaption\(\) and getTitle\(\)  [\#290](https://github.com/atk4/data/issues/290)
- Easier access to title of a current record [\#289](https://github.com/atk4/data/issues/289)

**Closed issues:**

- add travis testing for postgresql [\#288](https://github.com/atk4/data/issues/288)

**Merged pull requests:**

- Implement caption, getTitle, getModelCaption [\#299](https://github.com/atk4/data/pull/299) ([DarkSide666](https://github.com/DarkSide666))

## 1.3.3

**Fixed bugs:**

- DSN without password [\#298](https://github.com/atk4/data/issues/298)
- adding reference multiple times does not produce error [\#239](https://github.com/atk4/data/issues/239)
- addTitle\(\) doesn't work for fields without \_id suffix [\#220](https://github.com/atk4/data/issues/220)
- looks like misspelled -\>table [\#212](https://github.com/atk4/data/issues/212)

**Closed issues:**

- Model-\>export: using ID as first level array key? [\#311](https://github.com/atk4/data/issues/311)
- Refactor to use SQLTestCase from atk4/core [\#258](https://github.com/atk4/data/issues/258)
- Docs: There is wrong description and examples [\#204](https://github.com/atk4/data/issues/204)
- action\('field'\) on expression should alias it to name of field. [\#190](https://github.com/atk4/data/issues/190)
- Typecast use of 'actual' clashes with persistence-\>update\(\) [\#186](https://github.com/atk4/data/issues/186)

**Merged pull requests:**

- Implement export\(\) with field values used as array keys [\#313](https://github.com/atk4/data/pull/313) ([DarkSide666](https://github.com/DarkSide666))
- Feature/add php callback field [\#310](https://github.com/atk4/data/pull/310) ([romaninsh](https://github.com/romaninsh))
- fix some docs [\#309](https://github.com/atk4/data/pull/309) ([DarkSide666](https://github.com/DarkSide666))
- Action field, fx and fx0 generate nice aliases [\#305](https://github.com/atk4/data/pull/305) ([DarkSide666](https://github.com/DarkSide666))
- fix \#212 [\#304](https://github.com/atk4/data/pull/304) ([DarkSide666](https://github.com/DarkSide666))
- fix \#239 [\#303](https://github.com/atk4/data/pull/303) ([DarkSide666](https://github.com/DarkSide666))
- Fix addTitle\(\) [\#302](https://github.com/atk4/data/pull/302) ([DarkSide666](https://github.com/DarkSide666))
- add driver property [\#301](https://github.com/atk4/data/pull/301) ([DarkSide666](https://github.com/DarkSide666))
- use normalizeDSN\(\) [\#300](https://github.com/atk4/data/pull/300) ([DarkSide666](https://github.com/DarkSide666))

## 1.3.4


**Closed issues:**

- Model with properties: when iterating over several loaded Items, changes to property are done on all Items [\#318](https://github.com/atk4/data/issues/318)

**Merged pull requests:**

- validate if methods exist in persistence before calling them [\#324](https://github.com/atk4/data/pull/324) ([DarkSide666](https://github.com/DarkSide666))
- validate if persistence have action\(\) method [\#323](https://github.com/atk4/data/pull/323) ([DarkSide666](https://github.com/DarkSide666))
- if it's array then it's treated as defaults. [\#322](https://github.com/atk4/data/pull/322) ([DarkSide666](https://github.com/DarkSide666))
- Support LOB fields loading [\#321](https://github.com/atk4/data/pull/321) ([DarkSide666](https://github.com/DarkSide666))
- fix newInstance\(\) [\#320](https://github.com/atk4/data/pull/320) ([DarkSide666](https://github.com/DarkSide666))


## 1.3.5

**Fixed bugs:**

- REGRESSION: unable to aggregate aggregate fields [\#326](https://github.com/atk4/data/issues/326)

**Merged pull requests:**

- fix \#326 [\#327](https://github.com/atk4/data/pull/327) ([romaninsh](https://github.com/romaninsh))

# 1.2.0

When upgrading to 1.2.x branch watch out if your "Model" has a validate() method. The
database connection will now use "utf8" so if you used non-utf8 before you might need
to convert chartsets.

 - Added support for validate() in your Models
 - Added support for classic DSN format (mysql://..)
 - Added support for single-table Array Persistence
 - Using charset=utf8 connection by default

## 1.2.1

IMPORTANT: Agile Data will on longer prefix model class with `Model_` is specified as string:

``` php
$this->hasOne('client_id', 'Client'); // refers to Client, not Model_Client
```

For more info see #244

- Now support Agile Core 1.3.0 #240
- Migrate to CodeCov and improve coverage #241 #242
- Improved [documentation for $mandatory and $required](http://agile-data.readthedocs.io/en/develop/fields.html#Field::$mandatory) #233
- Added [documentation for Hooks](http://agile-data.readthedocs.io/en/develop/hooks.html) #238
- Fix Persistence_Array usage without table #245 #246

## 1.2.2

Agile Data was created some time ago, before factory() implementation was completed in Agile Core. At
that time we had to make a decision how to better set the default class for the field, so we used
propreties such as `_default_class_addField`. Now that Agile Core allows us to specify `Seed`, we
have refactored some of that internal functionality to rely on factory(). This should not impact
your applications unless you are using custom persistence. #261

Additional fixes:

- Persistence to hold Model Prefix #152
- adding addTitle() now hides 'id' field #252 #253
- refLink() to pass $defaults #254
- couldn't connect to sqlite due to ;charset postfix in DSN #256 #257
- improve iterating models without id field #260


## 1.2.3

This version focuses on enabling you to define your own Field classes, such as more advanced Password
handling field. See example: https://github.com/atk4/login/blob/master/src/Field/Password.php

Introduced new way to verify field values with `$model->compare('age', 20);` which returs boolean. Also
in addition to `enum` property, you can use `values` property with a field now.

Rewrote Overview section of documentation and added new information on Fields and Static Persistence.

 - Added `Persistence_Static` #265
 - Implemented `$field->values` property #266
 - Added `Field->compare()` and `Model->compare()`
 - Improved support for user-defined fields (nee `Field\Password` from https://github.com/atk4/login) #259
 - Allow to specify join kind
 - Allow fields extended from `Field` class to be loaded from SQL #269
 - Started official support of 7.2.
 - Fixed typecasting when using Array persistence
 - Extra docs on: Fields, Static Persistence
 - Docs rewrite of Overiew section.
 

## 1.1

The main feature of this release is introduction of strong types. See [Type Converting](http://agile-data.readthedocs.io/en/develop/persistence.html?highlight=typecasting#type-converting).

- Added support for load_normalization (off by default) and typecasting #94, #109, #125, #129, #131, #140, #144, #160, #161, #162, #167, #168, #169, 
- Improved support for field flags (read_only, never_persist etc) #105, #106, #123, #166, #170
- Refactored join implementation #98, #99, #107
- Improved integration with Agile Toolkit UI #95
- Added support for strict_field_check (on by default)
- Refactoring load() and save() code.
- Added methods: duplicate, saveAs, saveAndUnload, asModel, newInstance, withPersistence, #111, #112
- Refactored references. Reference addField will inherit type. #157, #163, 
- Implemented $model->atomic(). CRUD operations are now atomic. #116
- Improved ref('link_id') for loaded models. added hasRef() method. #124, #164
- Huge number of new unit-tests (278->369), and added some advanced tests.
- Added documentation for Fields [link](http://agile-data.readthedocs.io/en/develop/fields.html) #117
- Expanded Persistence section [link](http://agile-data.readthedocs.io/en/develop/persistence.html?highlight=persistence#inserting-record-with-a-specific-id) (from insert records with specific id, down to actions)
- Removed use of 'relations'. We use 'References' everywhere now. #120, #127, #134, #135, #139
- Added documentation on Title field importing [link](http://agile-data.readthedocs.io/en/develop/references.html#importing-hasone-title) #122, #137
- Other documentation (typos)
- Dependencies updated (dsql, core) #121
- PRs: #79, #82, #83, #85, #88, #90, #94, #95, #97, #130, #151, #156, #171



## 1.1.1

- disabled type fetching from related entities when using ref->addField due to performance degradation
- hotfixed situations where never_persist fields are loaded when specified as part as onlyFields

## 1.1.2

Minimum stability fixes after the release #172, #173

## 1.1.3

Don't mark fields as dirty value has a fuzzy-match (4=="4"). Fix each() typecasting.

## 1.1.4

- Added implementation for addRef(). #176
- Improve boolean handling. #181
- Fix matching of incompatible types. #187
- Don't assume ID is int for SQL types #191

## 1.1.5

- Added documentation for TypeCasting (#189)
- Added 'typecast' and 'serialise' properties for fields (#184)
- Renamed 'struct' into 'object' and added 'array' to avoid confusion
- ID fields for SQL are no longer 'integer'. They can be anything now. (#191)
- General typecasting is now moved into generic Persistence
- Improved normalisaton, so that fields don't become dirty for no reason (#187, #195)
- Added persist_timezone and persist_format (#193)
- Added date_time_class so that you can use Carbon if you want
- Many new tests added
- Minor fixes (#177, #188)


## 1.1.6

- added `Model->refModel` as a method to fetch model of a relation (no loading attempts) (#198)
- fixed ancient nasty problem when cloning (#199)
- fixed error with deep joins / inserting new records (#200, #201)

## 1.1.7

Minor updates and bugfixes.

- Impred support for ID-less and read-only models (#211)
- Improved compatibility with "UnionModel" extension
- Made source code more friendly with PhpStorm and IDEs
- Upadated README.md fixing some problems with examples
- Expanded documentation by including some missed methods
- Added "SQL Extensions" documentation section


## 1.1.8

Added CSV Persistence


## 1.1.9

Added Field::getCaption()

## 1.1.10

Aggregate fields (hasMany->addField) will now coalesce results
to automatically display 0 even if no related record exist.


## 1.1.11

Added support for OR conditions.

http://agile-data.readthedocs.io/en/develop/conditions.html#adding-or-conditions


## 1.1.12

- hasMany->addField() now correctly pass additional options to expression field.
- Update README.

- Prevent warning by having $table_alias property defined in a model
- Include a proper release script

## 1.1.13

- Add $required property for fields #225
- Fix ID-less iteration of Arrays #222
- When persistence lacks field data, previous record value should be reset #221
- Fix caption for hasOne(.. ['caption'=>'blah']). #226
- Added release script

## 1.1.14

- Upgrade to Agile Core 1.2 branch

## 1.1.16

Update some bugs in documentation and fix #229.


## 1.1.17

Add support for $reference property on field, which will link 'country_id'
field created by hasOne() with reference object. This is important to properly
display dropdowns in UI

## 1.1.18

 - If return false from afterLoad hook then prevent yielding data row (#231)
 - add ability to override title name to addTitle(['field'=>'currency_name']);

# 1.0

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

## 1.0.2

Maintenance release to include some of the bugfixes.

* Change: classes `Field_One`, `Field_Many` and `Field_SQL_One` are renamed to `Relation_One`, `Relation_Many` and `Relation_SQL_One` respectively. (old classes will remain for compatibility until 1.2) #86
* Added: `hasMany()->addFields(['foo','bar']);` (#77)
* Fix: `addCondition('foo', ['a','b'])` no longer sets default value for field `foo` (#77)
* Fix: `addField(new Field(), 'name')` displays error to remind you to use `add(new Field(), 'name')` (#77)
* Change: traversing hasOne reference without loaded record no longer generates exception but gives you un-loaded model. (#78)
* Added: `hasOne('client_id', ['default'=>$d])` will now properly set default for `client_id` field (#78)
* Fix: When building sub-qureies, [alias is properly used](http://agile-data.readthedocs.io/en/develop/relations.html?highlight=alias#relation-aliases). (#78)
* Added: [Advanced documentation](http://agile-data.readthedocs.io/en/develop/advanced.html)
* Added: `Field->set($value)` as a shortcat for `$model['field'] = $value` #81
* Added: Support for `mandatory` flag on field. `addField('name', ['mandatory'=>true])` #87
* Fixes: #80, #85, 


## 1.0.1

This is our first maintenance release that solves several important issues.

* Change: `$m->insert()` will return ID and not clone of `$m`. Cloning was causing some problems.
* Change: calling ref() on object without active record will return you object without active record instead of exception
* Change: `$m->ref()` into non-existant hasOne() relation then [saving it will update field inside $m](http://agile-data.readthedocs.io/en/develop/relations.html?highlight=contact_id#relations-with-new-records).
* Added ability to call `save([$data])`, which will combine set() and save()
* Added [support for `$model->title_field`](http://agile-data.readthedocs.io/en/develop/model.html#title-field-and-id-field)
* added [$m->isDirty()](http://agile-data.readthedocs.io/en/develop/model.html#Model::isDirty)
* Added [afterUnload and beforeUnload hooks](http://agile-data.readthedocs.io/en/develop/model.html#hooks).
* Added support for [automated model reloading](http://agile-data.readthedocs.io/en/develop/expressions.html?highlight=reloading#model-reloading-after-save)
* Added support for advanced patterns described here:
* ability to implement [soft-delete](http://agile-data.readthedocs.io/en/develop/advanced.html#soft-delete), [audit](http://agile-data.readthedocs.io/en/develop/advanced.html#audit-fields)
* support to [override default method actions](http://agile-data.readthedocs.io/en/develop/model.html?highlight=hook#how-to-prevent-action), e.g. [delete()](shttp://agile-data.readthedocs.io/en/develop/advanced.html#soft-delete-that-overrides-default-delete)
* support to [verify updates](http://agile-data.readthedocs.io/en/develop/model.html?highlight=hook#how-to-verify-updates) with afterInsertQuery
* ability to create [fields with unique values](http://agile-data.readthedocs.io/en/develop/advanced.html#creating-unique-field)
* Added support for [Related Aliases](http://agile-data.readthedocs.io/en/develop/relations.html#relation-aliases). Now you can hasOne() hasMany() to itself.
* Fix: when you update field from join and then immediatelly save()
* Fix: when you join on existing field
* Fix: update and delete no longer try to use join or aliases
* Fix: setting different values twice wouldn't reset dirty status if you return to the original
* Fix: dirty flags are still available for `afterSave()` method (so that you know which fields were saved)
* Documented $m->withID();
* Updated to latest version of DSQL

Included PRs: #75, #74, #73, #72, #71, #70, #65, #63, #61, #58, #55

# Pre-releases

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
