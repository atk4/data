=======
Results
=======

When query is executed by :php:class:`Connection` or
`PDO <http://php.net/manual/en/pdo.query.php>`_, it will return an object that
can stream results back to you. The PDO class execution produces a
`Doctrine\DBAL\Result`_ object which
you can iterate over.

If you are using a custom connection, you then will also need a custom object
for streaming results.

The only requirement for such an object is that it has to be a
`Generator <http://php.net/manual/en/language.generators.syntax.php>`_.
In most cases developers will expect your generator to return sequence
of id=>hash representing a key/value result set.


.. todo::
write more
