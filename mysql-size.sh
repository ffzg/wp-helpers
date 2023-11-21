echo XXX

cat <<'__SQL__' | sudo mysql | tee mysql.size

SELECT
	TABLE_SCHEMA,
	TABLE_NAME,
	DATA_LENGTH,
	INDEX_LENGTH,
	(DATA_LENGTH + INDEX_LENGTH)  as total,
	( (DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024) AS `Size (MB)`
FROM
  information_schema.TABLES
ORDER BY
  TABLE_SCHEMA, TABLE_NAME
DESC;
__SQL__

