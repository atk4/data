:::{php:namespace} Atk4\Data\Persistence\Sql
:::

(databases)=

# Vendor support and Extensions

| Vendor     | Support  | PDO     | Dependency  |
| ---------- | -------- | ------- | ----------- |
| MySQL      | Full     | mysql:  | native, PDO |
| SQLite     | Full     | sqlite: | native, PDO |
| Oracle     | Untested | oci:    | native, PDO |
| PostgreSQL | Untested | pgsql:  | native, PDO |
| MSSQL      | Untested | mssql:  | native, PDO |

:::{note}
Most PDO vendors should work out of the box
:::

## 3rd party vendor support

| Class               | Support | PDO       | Dependency                   |
| ------------------- | ------- | --------- | ---------------------------- |
| Connection_MyVendor | Full    | myvendor: | https://github/test/myvendor |

See {ref}`new_vendor` for more details on how to add support for your driver.
