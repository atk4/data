
===============
Advanced Topics
===============

Agile Data allow you to implement various tricks. 


Audit Fields
============

If you wish to have a certain field inside your models that will be automatically changed
when the record is being updated, this can be easily implemented in Agile Data.

I will be looking to create the following fields:

- created_dts
- updated_dts
- created_by_user_id
- updated_by_user_id

To implement the above, I'll create a new class::

    class Controller_Audit {
        
        use \atk4\core\InitializerTrait {
            init as _init;
        }
        use \atk4\core\TrackableTrait;
        use \atk4\core\AppScopeTrait;

    }

TrackableTrait means that I'll be able to add this object inside model
with ``$model->add(new Controller_Audit())`` and that will automatically
populate $owner, and $app values (due to AppScopeTrait) as well as 
execute init() method, which I want to define like this::


    function init() {
        $this->_init();

        if(isset($this->owner->no_audit)){
            return;
        }

        $this->owner->addField('created_dts', ['type'=>'datetime', 'default'=>date('Y-m-d H:i:s')]);

        $this->owner->hasOne('created_by_user_id', 'User');
        if(isset($this->app->user) and $this->app->user->loaded()) {
            $this->owner->getElement('created_by_user_id')->default = $this->app->user->id;
        }

        $this->owner->hasOne('updated_by_user_id', 'User');

        $this->owner->addField('updated_dts', ['type'=>'datetime']);

        $this->owner->addHook('beforeUpdate', function($m, $data) {
            if(isset($this->app->user) and $this->app->user->loaded()) {
                $data['updated_by'] = $this->app->user->id;
            }
            $data['updated_dts'] = date('Y-m-d H:i:s');
        });
    }

In order to add your defined behaviour to the model. The first check actually allows you to define
models that will bypass audit alltogether::

    $u1 = new Model_User($db);   // Model_User::init() includes audit

    $u2 = new Model_User($db, ['no_audit' => true]);  // will exclude audit features

Next we are going to define 'created_dts' field which will default to the current date and time.

The default value for our 'created_by_user_id' field would depend on a currently-logged in user,
which would typically be accessible through your application. AppScope allows you to pass
$app arround through all the objects, which means that your Audit Controller will be able
to get the current user.

Of course if the application is not defined, no default is set. This would be handy for
unit tests where you could manually specify the value for this field.

The last 2 fields (update_*) will be updated through a hook - beforeSave() and will
provide the values to be saved during ``save()``. beforeUpdate() will not be called when
new record is inserted, so those fields will be left as "null" after initial insert.

If you wish, you can modify the code and insert historical records into other table.

Soft Delete
===========

Most of the data frameworks provide some way to enable 'soft-delete' for tables as
a core feature. Design of Agile Data makes it possible to implement soft-delete
through external controller. There may be a 3rd party controller for comprehensive
soft-delete, but in this section I'll explain how you can easily build your own
soft-delete controller for Agile Data (for educational purposes).

Start by creating a class::

    class Controller_SoftDelete {
        
        use \atk4\core\InitializerTrait {
            init as _init;
        }
        use \atk4\core\TrackableTrait;

        function init() {
            $this->_init();

            if(isset($this->owner->no_soft_delete)){
                return;
            }

            $this->owner->addField('is_deleted', ['type'=>'boolean']);

            if (isset($this->owner->deleted_only)) {
                $this->owner->addCondition('is_deleted', true);
                $this->owner->addMethod('restore', $this);
            }else{
                $this->owner->addCondition('is_deleted', false);
                $this->owner->addMethod('softDelete', $this);
            }
        }

        function softDelete($m) {
            if (!$m->loaded()) {
                throw new \atk4\core\Exception(['Model must be loaded before soft-deleting', 'model'=>$m]);
            }

            $id = $m->id;
            if ($m->hook('beforeSoftDelete') === false) {
                return $m;
            }

            $rs = $m->reload_after_save;
            $m->reload_after_save = false;
            $m->save(['is_deleted'=>true])->unload();
            $m->reload_after_save = $rs;

            $m->hook('afterSoftDelete', [$id]);
            return $m;
        }

        function restore($m) {
            if (!$m->loaded()) {
                throw new \atk4\core\Exception(['Model must be loaded before restoring', 'model'=>$m]);
            }

            $id = $m->id;
            if ($m->hook('beforeRestore') === false) {
                return $m;
            }

            $rs = $m->reload_after_save;
            $m->reload_after_save = false;
            $m->save(['is_deleted'=>false])->unload();
            $m->reload_after_save = $rs;

            $m->hook('afterRestore', [$id]);
            return $m;
        }
    }

This implementation of soft-delete can be turned off by setting model's property 'no_soft_delete'
to true (if you want to recover a record).

When active, a new field will be defined 'is_deleted' and a new dynamic method will be added into
a model, allowing you to do this::

    $m = new Model_Invoice($db);
    $m->load(10);
    $m->softDelete();

The method body is actually defined in our controller. Notice that we have defined 2
hooks - beforeSoftDelete and afterSoftDelete that work similarly to beforeDelete and afterDelete.

beforeSoftDelete will allow you to "break" it in certain cases to bypass the rest of method, again,
this is to maintain conistency with the rest of before* hooks in Agile Data.

Hooks are called through the model, so your call-back will autamtically receive first argument
$m, and afterSoftDelete will pass second argument - $id of deleted record.

I am then setting reload_after_save value to false, because after I set 'is_deleted' to false,
$m will no longer be able to load the record - it will fall outside of the DataSet. (We
might implement a better method for saving records outside of DataSet in the future).

After softDelete active record is unloaded, mimicking behaviour of delete().

It's also possible for you to easily look at deleted records and even restore them::

    $m = new Model_Invoice($db, ['deleted_only'=>true]);
    $m->load(10);
    $m->restore();

Note that you can call $m->delete() still on any record to permanently delete it.

Soft Delete that overrides default delete()
-------------------------------------------

In case you want $m->delete() to perform soft-delete for you - this can also be achieved through
a pretty simple controller. In fact I'm reusing the one from before and just slightly modifying
it::

    class Controller_SoftDelete {
        
        use \atk4\core\InitializerTrait {
            init as _init;
        }
        use \atk4\core\TrackableTrait;

        function init() {
            $this->_init();

            if(isset($this->owner->no_soft_delete)){
                return;
            }

            $this->owner->addField('is_deleted', ['type'=>'boolean']);

            if (isset($this->owner->deleted_only)) {
                $this->owner->addCondition('is_deleted', true);
                $this->owner->addMethod('restore', $this);
            } else {
                $this->owner->addCondition('is_deleted', false);
                $this->owner->addHook('beforeDelete', [$this, 'softDelete'], null, 100);
            }
        }

        function softDelete($m) {
            if (!$m->loaded()) {
                throw new \atk4\core\Exception(['Model must be loaded before soft-deleting', 'model'=>$m]);
            }

            $id = $m->id;

            $rs = $m->reload_after_save;
            $m->reload_after_save = false;
            $m->save(['is_deleted'=>true])->unload();
            $m->reload_after_save = $rs;

            $m->hook('afterDelete', [$id]);

            $m->breakHook(false); // this will cancel original delete()
        }

        function restore($m) {
            if (!$m->loaded()) {
                throw new \atk4\core\Exception(['Model must be loaded before restoring', 'model'=>$m]);
            }

            $id = $m->id;
            if ($m->hook('beforeRestore') === false) {
                return $m;
            }

            $rs = $m->reload_after_save;
            $m->reload_after_save = false;
            $m->save(['is_deleted'=>false])->unload();
            $m->reload_after_save = $rs;

            $m->hook('afterRestore', [$id]);
            return $m;
        }
    }

Implementation of this controller is similar to the one above, however instead of creating softDelete()
it overrides the delete() method through a hook. It will still call 'afterDelete' to mimic the
behaviour of regular delete() after the record is marked as deleted and unloaded.

You can still access the deleted records::

    $m = new Model_Invoice($db, ['deleted_only'=>true]);
    $m->load(10);
    $m->restore();

Calling delete() on the model with 'deleted_only' property will delete it permanently.


