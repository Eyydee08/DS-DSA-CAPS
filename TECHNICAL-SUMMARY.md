# Technical Summary

The application stores archive metadata in MySQL using PDO and prepared
statements. Archive categories are normalized into `categories`; each `archives`
record references one category through `category_id`. The archive table stores
the primary attachment path, total attachment size in kilobytes, year,
description, and creation time. Attachment records are kept in the related
`archive_files` table, while file contents stay outside the public web root.

When a user opens the archive list, the controller retrieves matching raw
records from MySQL. Keyword and category filters are applied by parameterized
SQL. The resulting records are then sorted in PHP by archive year descending,
with archive ID descending as the tie-breaker, using the bottom-up MergeSort in
`dsa/merge_sort.php`. The sorted records are passed to the view for rendering.
This keeps the chosen sorting algorithm in the in-memory processing layer rather
than relying on SQL `ORDER BY`.

For **n** matching records, MergeSort performs **O(n log n)** comparisons and
uses **O(n)** auxiliary space. Database retrieval and filtering are separate
operations; their cost depends on the database size and indexes.
