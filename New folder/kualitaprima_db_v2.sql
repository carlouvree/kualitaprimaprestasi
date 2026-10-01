-- ================================================================
-- PT. Kualita Prima Prestasi — Database Seed v2
-- Import file ini ke phpMyAdmin setelah melakukan import kualitaprima_db.sql
-- File ini hanya berisi DATA SEED untuk tabel categories
-- ================================================================

-- Hapus data lama jika ada
TRUNCATE TABLE `categories`;

-- Kategori JUAL
INSERT INTO `categories` (`name`, `type`) VALUES ('Batching Plant', 'jual');
INSERT INTO `categories` (`name`, `type`) VALUES ('Crusher Plant', 'jual');
INSERT INTO `categories` (`name`, `type`) VALUES ('Sand Washing Plant', 'jual');
INSERT INTO `categories` (`name`, `type`) VALUES ('Machines Parts', 'jual');

-- Kategori SEWA
INSERT INTO `categories` (`name`, `type`) VALUES ('Excavators', 'sewa');
INSERT INTO `categories` (`name`, `type`) VALUES ('Mobile Cranes', 'sewa');
INSERT INTO `categories` (`name`, `type`) VALUES ('Wheel Loader', 'sewa');
INSERT INTO `categories` (`name`, `type`) VALUES ('Bulldozers', 'sewa');
