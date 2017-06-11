# XenForo-SearchImprovements

A Collection of improvements to XF's Enhanced Search (Elastic Search). Does NOT work with MySQL search.

- range_query search DSL
 - allows arbitrary range queries for numerical data
- Allow users to select the default search order independent for the forum wide setting.
- Per content type weighting
- Adds Elastic Search information to the AdminCP home screen.
- Adds a debug option to log the search DSL queries to error log for troubleshooting
- Option to extend search syntax to permit;
 - + signifies AND operation
 - | signifies OR operation
 - - negates a single token
 - " wraps a number of tokens to signify a phrase for searching
 - * at the end of a term signifies a prefix query
 - ( and ) signify precedence
 - ~N after a word signifies edit distance (fuzziness)
 - ~N after a phrase signifies slop amount