<?php
/** Return the order activity date used by the reorder history timeline. */
function customerReorderTimelineDateSql($orderAlias = 'o')
{
    return "CASE WHEN {$orderAlias}.modifiedDate >= '2000-01-01' THEN DATE({$orderAlias}.modifiedDate) WHEN {$orderAlias}.documentDate >= '2000-01-01' THEN DATE({$orderAlias}.documentDate) ELSE DATE({$orderAlias}.createdDate) END";
}
