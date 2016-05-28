
============================
Working With Business Models
============================

.. php:class:: Model

Model class implements your Business Model - single entity of your business logic. When
you plan your business application you should create classes for all your possible
business models by extending from "Model" class. 

.. php:method:: init

Method init() will automatically be called when your Model is associated with persistence
driver::

    class Model_User extends atk4\data\Model
    {
        function init() {
            parent::init();

            $this->addField('name');
            $this->addField('surname');
        }
    }

and is a good location where to define fields for your model.


.. php:method:: addField($name, $defaults)

    Creates a new field objects inside your model (by default the class is 'Field'). 
    The fields are implemented on top of Containers from Agile Core.

You can create as many fields as you need as long as their names are unique. 




 WRITE MORE


