# detecting active transaction

- easy in MariaDB: "select @@in_transaction"
- almost impossible in MySQL - the best working solution seems to be https://stackoverflow.com/questions/30691203/mysql-how-to-check-if-start-transaction-is-active/36840813#36840813
  ie. try to modify the current isolation level and check if an error is emit or not

# transaction id

https://stackoverflow.com/questions/26620966/getting-the-current-transaction-id-with-mysql
https://dba.stackexchange.com/questions/128726/transaction-identifier-possible-with-mysql
https://vladmihalcea.com/current-database-transaction-id/

- https://dba.stackexchange.com/questions/128726/transaction-identifier-possible-with-mysql#comment239993_129055 seems the best solution

# isolation - "general"

https://jepsen.io/analyses/mysql-8.0.34
https://jepsen.io/blog/2024-11-07-mariadb-snapshot-isolation -> "innodb_snapshot_isolation" was introduced in MariaDB 10.6.18 and is enabled by default since 11.6.2

# isolation - "two bank accounts usecase"

https://aphyr.com/posts/327-call-me-maybe-mariadb-galera-cluster#designing-a-test
related ticket https://github.com/codership/galera/issues/336

even "repeatable read" might be too weak if data are not queried in one select and even "serializable" might be too weak as it has bugs

# isolation - "our autoincrement"

https://siemens.blog/posts/prevent-write-skew-with-select-for-update/

TODO
