#!/bin/sh

cat <<'__SQL__' | sudo mysql > mysql.tables

SELECT
	*
FROM
  information_schema.TABLES
WHERE
	DATA_LENGTH is not NULL
ORDER BY
  TABLE_SCHEMA, TABLE_NAME
__SQL__

