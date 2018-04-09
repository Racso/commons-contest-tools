PRAGMA foreign_keys = off;
BEGIN TRANSACTION;

-- Table: ids
CREATE TABLE 'ids' ('title' TEXT NOT NULL, 'id' INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL);

-- Table: judges
CREATE TABLE 'judges' ('timestamp' TEXT, 'judge' TEXT, 'photoid' INTEGER, 'score' INTEGER);

-- Table: judgesData
CREATE TABLE 'judgesData' ('user' TEXT PRIMARY KEY NOT NULL, 'pass' TEXT NOT NULL);

-- Table: metadata
CREATE TABLE 'metadata' ('name' TEXT PRIMARY KEY NOT NULL, 'value' TEXT);

-- Table: photos
CREATE TABLE 'photos' ('title' TEXT, 'author' TEXT, 'place' TEXT, PRIMARY KEY ("title") );

-- Table: places
CREATE TABLE 'places' ('id' TEXT PRIMARY KEY NOT NULL, 'topName' TEXT, 'name' TEXT);

-- Table: thumbs
CREATE TABLE 'thumbs' ('title' TEXT PRIMARY KEY NOT NULL, 'url' TEXT);

-- Index: ids_unique_index
CREATE UNIQUE INDEX 'ids_unique_index' ON "ids" ("title" ASC);

COMMIT TRANSACTION;
PRAGMA foreign_keys = on;
