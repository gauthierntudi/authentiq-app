-- MySQL dump 10.13  Distrib 9.6.0, for macos26.2 (arm64)
--
-- Host: 127.0.0.1    Database: authentiq.db
-- ------------------------------------------------------
-- Server version	9.6.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
SET @MYSQLDUMP_TEMP_LOG_BIN = @@SESSION.SQL_LOG_BIN;
SET @@SESSION.SQL_LOG_BIN= 0;

--
-- GTID state at the beginning of the backup 
--

SET @@GLOBAL.GTID_PURGED=/*!80000 '+'*/ 'f7131600-2063-11f0-9f41-2e0489a3980d:1-164';

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('authentiq-cache-illuminate:queue:restart','i:1779334494;',2094694494);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `CLIENTS`
--

DROP TABLE IF EXISTS `CLIENTS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CLIENTS` (
  `id_client` int NOT NULL AUTO_INCREMENT,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `nom_complet` varchar(255) DEFAULT NULL,
  `tel` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `id_province` int DEFAULT NULL,
  `id_ville` int DEFAULT NULL,
  `adresse` text,
  `type_piece_identite` varchar(50) DEFAULT NULL,
  `numero_national` varchar(50) DEFAULT NULL,
  `numero_passeport` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `aws_face_id` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id_client`),
  KEY `id_province` (`id_province`),
  KEY `clients_ibfk_2` (`id_ville`),
  CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`id_province`) REFERENCES `PROVINCES` (`id_province`),
  CONSTRAINT `clients_ibfk_2` FOREIGN KEY (`id_ville`) REFERENCES `VILLES` (`id_ville`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `CLIENTS`
--

LOCK TABLES `CLIENTS` WRITE;
/*!40000 ALTER TABLE `CLIENTS` DISABLE KEYS */;
INSERT INTO `CLIENTS` VALUES (2,0,'Okito Mata David','0851208817','salondelemploirdc@gmail.com',NULL,1,1,NULL,'CNI','8893652','99837565','2025-09-30 19:35:32',NULL),(4,1,'Albert Camus','0846516270','kipaobuildsarlu@gmail.com',NULL,1,1,'28 De la paix Cité maman Mobutu, Q/dimese Mont Ngafula','CNI','8893611','99837599','2025-09-30 19:35:32',NULL),(7,1,'Gauthier Ntudi','0824269291','gauthierntudi@gmail.com','uploads/clients/client_7_6a0ed77599e88.jpg',1,1,'','Passeport','8893666','96737577','2025-09-30 19:35:32',NULL),(8,1,'Yen Docs','0824444442','',NULL,NULL,NULL,NULL,'Passeport','992773653',NULL,'2025-11-21 15:33:08',NULL),(9,1,'Alexandre Mulumba','0824269200','grandmarcherdc@gmail.com','uploads/clients/client_9_6a0ef3e6580ee.jpg',1,1,'553 Malemba Nkulu Masina','CNI','00112277','','2026-05-21 12:00:38',NULL);
/*!40000 ALTER TABLE `CLIENTS` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `COMMUNES`
--

DROP TABLE IF EXISTS `COMMUNES`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `COMMUNES` (
  `id_commune` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `id_ville` int NOT NULL,
  PRIMARY KEY (`id_commune`),
  KEY `id_ville` (`id_ville`),
  CONSTRAINT `communes_ibfk_1` FOREIGN KEY (`id_ville`) REFERENCES `VILLES` (`id_ville`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `COMMUNES`
--

LOCK TABLES `COMMUNES` WRITE;
/*!40000 ALTER TABLE `COMMUNES` DISABLE KEYS */;
INSERT INTO `COMMUNES` VALUES (1,'Mont Ngafula',1),(2,'N\'djili',1),(3,'Matete',1),(4,'Limete',1),(5,'Gombe',1),(6,'Nzanza',4),(7,'Sokol',5);
/*!40000 ALTER TABLE `COMMUNES` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `DOCS`
--

DROP TABLE IF EXISTS `DOCS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DOCS` (
  `id_doc` int NOT NULL AUTO_INCREMENT,
  `nom_doc` varchar(255) DEFAULT NULL,
  `type_doc` enum('free','payant') DEFAULT NULL,
  `montant` decimal(10,2) DEFAULT NULL,
  `duree` int DEFAULT '0' COMMENT '0 = durée illimitée, sinon durée en mois',
  `validite` enum('court','moyen','long') DEFAULT 'court',
  `fields_config` json DEFAULT NULL,
  `expiration_seconds` int DEFAULT NULL,
  PRIMARY KEY (`id_doc`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `DOCS`
--

LOCK TABLES `DOCS` WRITE;
/*!40000 ALTER TABLE `DOCS` DISABLE KEYS */;
INSERT INTO `DOCS` VALUES (1,'Attestation de Naissance','free',0.00,6,'court',NULL,NULL),(2,'Acte de Mariage','payant',2.00,0,'long',NULL,NULL),(3,'Attestation de perte de pièces','payant',3.00,12,'court',NULL,NULL),(4,'Livret Parcellaire','payant',20.00,0,'long',NULL,NULL),(5,'Permis de construire','payant',5.00,60,'moyen',NULL,NULL);
/*!40000 ALTER TABLE `DOCS` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ENCODAGES`
--

DROP TABLE IF EXISTS `ENCODAGES`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ENCODAGES` (
  `id_encodage` int NOT NULL AUTO_INCREMENT,
  `id_doc` int DEFAULT NULL,
  `id_client` int DEFAULT NULL,
  `id_user` int DEFAULT NULL,
  `type_doc` varchar(50) DEFAULT NULL,
  `montant` decimal(10,2) DEFAULT NULL,
  `id_province` int DEFAULT NULL,
  `id_ville` int DEFAULT NULL,
  `affectation` varchar(255) DEFAULT NULL,
  `files` text,
  `ocrTextFiles` text,
  `date_emission` date DEFAULT NULL,
  `date_expiration` date DEFAULT NULL,
  `id_commune` int DEFAULT NULL,
  `status` enum('incomplete','complete','expired') NOT NULL DEFAULT 'incomplete',
  `page_count` int DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `numero` varchar(64) DEFAULT NULL,
  `qr_path` varchar(512) DEFAULT NULL,
  `extracted_data` json DEFAULT NULL,
  `qrcode_path` varchar(500) DEFAULT NULL,
  `client_photo_path` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id_encodage`),
  UNIQUE KEY `numero` (`numero`),
  KEY `id_doc` (`id_doc`),
  KEY `id_client` (`id_client`),
  KEY `id_province` (`id_province`),
  KEY `id_ville` (`id_ville`),
  KEY `id_commune` (`id_commune`),
  KEY `idx_encodages_status` (`status`),
  KEY `idx_encodages_user_status` (`id_user`,`status`),
  KEY `idx_encodages_created` (`created_at`),
  KEY `idx_encodages_user_commune_status` (`id_user`,`id_commune`,`status`),
  CONSTRAINT `encodages_ibfk_1` FOREIGN KEY (`id_doc`) REFERENCES `DOCS` (`id_doc`),
  CONSTRAINT `encodages_ibfk_2` FOREIGN KEY (`id_client`) REFERENCES `CLIENTS` (`id_client`),
  CONSTRAINT `encodages_ibfk_3` FOREIGN KEY (`id_user`) REFERENCES `USERS` (`id_user`),
  CONSTRAINT `encodages_ibfk_4` FOREIGN KEY (`id_province`) REFERENCES `PROVINCES` (`id_province`),
  CONSTRAINT `encodages_ibfk_5` FOREIGN KEY (`id_ville`) REFERENCES `VILLES` (`id_ville`),
  CONSTRAINT `encodages_ibfk_6` FOREIGN KEY (`id_commune`) REFERENCES `COMMUNES` (`id_commune`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ENCODAGES`
--

LOCK TABLES `ENCODAGES` WRITE;
/*!40000 ALTER TABLE `ENCODAGES` DISABLE KEYS */;
INSERT INTO `ENCODAGES` VALUES (1,NULL,8,1,NULL,NULL,1,1,'Gombe','../uploads/fileAuthentiq/doc_1_p1_6920861ae651f.jpg','--- Page 1 ---\n',NULL,NULL,5,'incomplete',1,'2025-11-21 15:32:42','2026-05-21 11:41:24',NULL,NULL,NULL,NULL,NULL),(2,3,2,4,'payant',3.00,1,1,'Gombe','uploads/fileAuthentiq/doc_2_p1_6a0e6cdda9857.jpg','--- Page 1 ---\nSNIP\nconçue Le essentielles Système pour Numérique collecter. centraliser d\'Information et Policière (SNIP) est une initiative\nliées à la sécurité. au contrôle transmettre et au maintien instantanément de l\'ordre public des informations innovante l\'État.\nconcernant Son objectif les principal est de faciliter l\'accès à des données par\npermettant de crimes, infractions. plaintes, contentieux, ainsi complètes tout et fiables\nl\'ensemble retracer les antécédents criminels et infractionnels que autre élément\nzones les plus reculées et difficile d\'accès.\ndu territoire de la République Démocratique du Congo, y compris d\'un individu dans les sur\nMéthodologie de mise en ceuvre Approche en deux phases\nPhase 1 : Constitution de la Base de Données Nationale\nObjectif :\nCréer une base de données nationale solide à partir des documents existants, en\nprocédant à la numérisation des procès-verbaux (PV), plaintes et archives policières\nrelatives aux infractions et activités criminelles établies au cours des cinq (5) à sept (7)\ndernières années.\nÉtapes :\nCollecte systématique des archives physiques dans tous les\ncommissariats, sous-commissariats et unités de police.\net encodages de chaque élément constituant un dossier etc.).\nNumérisation identités, faits, lieux, dates, preuves, pièces centralisée, jointes,\nOrganisation (infractions, de ces informations dans un système\nstructurée et exploitable.\nImpact attendu\nConstitution : permettant d\'une base d\'établir de données un historique nationale des fiable antécédents d\'archives le croisements criminels,\npolicières et plaintes\npermettant','2026-05-21','2027-05-21',5,'complete',1,'2026-05-21 01:24:29','2026-05-21 11:41:32',NULL,NULL,NULL,NULL,NULL),(3,1,4,4,'free',0.00,1,1,'Gombe','uploads/fileAuthentiq/doc_3_p1_6a0e6dbade115.jpg','--- Page 1 ---\nRecherche Recherche digitales. par reconnaissance faciale, vocale ou d\'empreintes\nAnalyse cartographique alphanumérique de et croisement d\'informations\n*\nTriangulation et Suivi des éléments chaque activités\nTransmission d\'avis de recherche et connectés\nSystème de communication sécurisé autres entre agents\nCapaone d\'interconnexion avec tout autre bases de données nationales\net internationales\nAvantages du SNIP\nRenforcement de la sécurité nationale\nLe SNIP améliore la capacité des forces de l\'ordre à protéger la\npopulation.\nL \'intégration de technologies avancées placera la RDC à la pointe de la\nTechnologie au service de la protection publique\nlutte contre la criminalité.\nDissuasion permettra du banditisme d\'anticiper et des dérives et de d\'informations. prévenir sociales les actes délictueux en\ncomblant Le système les lacunes liées au manque','2026-05-13','2026-11-13',5,'complete',1,'2026-05-21 01:28:10','2026-05-21 11:41:40',NULL,NULL,NULL,NULL,NULL),(4,NULL,2,4,NULL,NULL,1,1,'Gombe','uploads/fileAuthentiq/doc_4_p1_6a0e7156e2af4.jpg','--- Page 1 ---\nSNIP\nessentielles conçue Le Système pour Numérique collecter. d\'Information\nconcernant Son objecte liées principal à la sécurité. est centraliser au Policière 1 innovante\nl\'ensemble permettant zones les plus du de les territoire reculées retracer crimes. infractions. de les et de antécédents faciliter plaintes. l\'accès contentieux. à des données ainsi complètes y tout d\'un compris autre individu et dans élément fiables sur\nles\nMéthodologie de mise en œuvre - Approche en deux phases\nPhase 1 : Constitution de la Base de Données Nationale\nObjectif :\nprocédant Créer une à base la de données nationale solide à partir des documents\nrelatives numérisation des procès-verbaux (PV), plaintes et archives existants, en\ndemières aux années. infractions et activités criminelles établies au cours des cinq (5) policières à sept (7)\nÉtapes :\nCollecte systématique des archives physiques dans tous les\ncommissariats, sous-commissariats et unités de police.\nNumérisation et encodages de chaque élément constituant un dossier\n(infractions, identités, faits, lieux, dates, preuves, pièces jointes, etc.).\nOrganisation de ces informations dans un système centralisée,',NULL,NULL,5,'incomplete',1,'2026-05-21 01:43:34','2026-05-21 11:41:45',NULL,NULL,NULL,NULL,NULL),(5,3,7,4,'payant',3.00,1,1,'Gombe','fileAuthentiq/doc_5_p1_6a0e7ef40ed1b.jpg','--- Page 1 ---\nSNIP\nLe Système Numérique d\'Information Policière (SNIP) est une initiative\nessentielles libes à la sécurité, au contrôle et au maintien de l\'ordre public par l\'État.\nconçue pour collecter, centraliser et transmettre instantanément des informations innovante\nSon objectif principal est de faciliter l\'accès à des données complètes et fiables\nconcernant les crimes, infractions, plaintes, contentieux, ainsi que tout autre élément\npermettant de retracer les antécédents criminels et infractionnels d\'un individu sur\nl\'ensemble du territoire de la République Démocratique du Congo, y compris dans les\nzones les plus reculées et difficile d\'accès.\nMéthodologie de mise en ceuvre - Approche en deux phases\nPhase 1 : Constitution de la Base de Données Nationale\nObjectif : base de données nationale solide à partir des documents archives existants, policières en\nCréer une numérisation des procès-verbaux (PV). plaintes et (5) à sept (7)\nprocédant relatives aux à la infractions et activités criminelles établies au cours des cinq\ndernières années.','2026-05-21','2027-05-21',5,'complete',1,'2026-05-21 02:41:40','2026-05-21 11:25:52','3224602A','fileAuthentiq/qr_3224602A.png',NULL,NULL,NULL),(6,NULL,NULL,4,NULL,NULL,1,1,'Gombe','fileAuthentiq/doc_6_p1_6a0eee156ecc2.jpg','--- Page 1 ---\nACCORD DE CONFIDENTIALITÉ\n- — informed - La societé \" du technology\nENTRE LES SOUSSIGNES\nMadama Innocente commune de Ngaliema, a Kimhasa on\nI ukuwa Mbemba nee a Kinshasa, la 11/03/1996 Republique résident sur l\'avense\nDemocratique Avenue memero du 8. Congu numbro de passeport OF1964009P d-après dénommine la . Partie\nDivelgatrice\nD\'UNE PART\nET\nDataxell ayant son siege au or niveau, Immenble Goll Appart. n°21, Boulevard\nLa unit commone de la Gombe. Kinihasa Republique Démocratique du Congo,\ndu 10 Jain au RCCM was le numtro CD/KNM/RCCM/20-A-01-722, identifiee sous to\nnuméro immatriculée ID NAT 01-G4-701-N71406N a le numéro d\'impôt A2321361U dûment\nrepresentee pur LON gerent. Mr Cauthier Ntudi,\nLT\nGauthier Ntudi ne 1 Kinshasa le 04/08/1990, résidant a 28. Avenue de La Pan.\nQuartier Mommer Baruti commune de Mont-Ngafula, Kinshasa Republique Démocratique du Congo,\nnuméro de passeport OP1769933\nci-apres conjectement a solidairement dénommés la \" Partie Receptrice a\nD\'AUTRE PART\nPRÉAMBULE\nDans le cadre des prestations de services informatiques confiées par la Partie Divulgatrice la\nPartia Receptrice vera amende à accéder à certaines informations présentant un caractere\ninclement confidentiel. notamment des données à caractère personnel telles que des numéros\nde telephone\nLes Parties revensament que ces informations sont sensibles et que toute divulgation ou\natilisation non autorisée est susceptible de causer un préjudice grave, tant à la Partie\nDivelgatrice qu\'aux personnes concernees\nEn consiquence. il a etc convenu ce qui suit',NULL,NULL,5,'incomplete',1,'2026-05-21 10:35:49','2026-05-21 11:26:04',NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `ENCODAGES` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ENCODAGES_PAGES`
--

DROP TABLE IF EXISTS `ENCODAGES_PAGES`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ENCODAGES_PAGES` (
  `id_page` int NOT NULL AUTO_INCREMENT,
  `id_encodage` int NOT NULL,
  `page_number` int NOT NULL COMMENT 'Numéro de la page (1, 2, 3...)',
  `file_path` varchar(500) NOT NULL COMMENT 'Chemin du fichier scanné',
  `file_size` int DEFAULT NULL COMMENT 'Taille du fichier en bytes',
  `ocr_text` text COMMENT 'Texte OCR de cette page spécifique',
  `textract_status` varchar(20) DEFAULT NULL,
  `quality_score` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_page`),
  KEY `idx_encodage` (`id_encodage`),
  KEY `idx_page_number` (`page_number`),
  CONSTRAINT `fk_pages_encodage` FOREIGN KEY (`id_encodage`) REFERENCES `ENCODAGES` (`id_encodage`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb3 COMMENT='Pages scannées pour chaque encodage';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ENCODAGES_PAGES`
--

LOCK TABLES `ENCODAGES_PAGES` WRITE;
/*!40000 ALTER TABLE `ENCODAGES_PAGES` DISABLE KEYS */;
INSERT INTO `ENCODAGES_PAGES` VALUES (1,1,1,'../uploads/fileAuthentiq/doc_1_p1_6920861ae651f.jpg',495324,'','succeeded',NULL,'2025-11-21 15:32:42'),(2,2,1,'uploads/fileAuthentiq/doc_2_p1_6a0e6cdda9857.jpg',360505,'SNIP\nconçue Le essentielles Système pour Numérique collecter. centraliser d\'Information et Policière (SNIP) est une initiative\nliées à la sécurité. au contrôle transmettre et au maintien instantanément de l\'ordre public des informations innovante l\'État.\nconcernant Son objectif les principal est de faciliter l\'accès à des données par\npermettant de crimes, infractions. plaintes, contentieux, ainsi complètes tout et fiables\nl\'ensemble retracer les antécédents criminels et infractionnels que autre élément\nzones les plus reculées et difficile d\'accès.\ndu territoire de la République Démocratique du Congo, y compris d\'un individu dans les sur\nMéthodologie de mise en ceuvre Approche en deux phases\nPhase 1 : Constitution de la Base de Données Nationale\nObjectif :\nCréer une base de données nationale solide à partir des documents existants, en\nprocédant à la numérisation des procès-verbaux (PV), plaintes et archives policières\nrelatives aux infractions et activités criminelles établies au cours des cinq (5) à sept (7)\ndernières années.\nÉtapes :\nCollecte systématique des archives physiques dans tous les\ncommissariats, sous-commissariats et unités de police.\net encodages de chaque élément constituant un dossier etc.).\nNumérisation identités, faits, lieux, dates, preuves, pièces centralisée, jointes,\nOrganisation (infractions, de ces informations dans un système\nstructurée et exploitable.\nImpact attendu\nConstitution : permettant d\'une base d\'établir de données un historique nationale des fiable antécédents d\'archives le croisements criminels,\npolicières et plaintes\npermettant','succeeded',93.95,'2026-05-21 01:24:29'),(3,3,1,'uploads/fileAuthentiq/doc_3_p1_6a0e6dbade115.jpg',529058,'Recherche Recherche digitales. par reconnaissance faciale, vocale ou d\'empreintes\nAnalyse cartographique alphanumérique de et croisement d\'informations\n*\nTriangulation et Suivi des éléments chaque activités\nTransmission d\'avis de recherche et connectés\nSystème de communication sécurisé autres entre agents\nCapaone d\'interconnexion avec tout autre bases de données nationales\net internationales\nAvantages du SNIP\nRenforcement de la sécurité nationale\nLe SNIP améliore la capacité des forces de l\'ordre à protéger la\npopulation.\nL \'intégration de technologies avancées placera la RDC à la pointe de la\nTechnologie au service de la protection publique\nlutte contre la criminalité.\nDissuasion permettra du banditisme d\'anticiper et des dérives et de d\'informations. prévenir sociales les actes délictueux en\ncomblant Le système les lacunes liées au manque','succeeded',89.96,'2026-05-21 01:28:10'),(4,4,1,'uploads/fileAuthentiq/doc_4_p1_6a0e7156e2af4.jpg',367689,'SNIP\nessentielles conçue Le Système pour Numérique collecter. d\'Information\nconcernant Son objecte liées principal à la sécurité. est centraliser au Policière 1 innovante\nl\'ensemble permettant zones les plus du de les territoire reculées retracer crimes. infractions. de les et de antécédents faciliter plaintes. l\'accès contentieux. à des données ainsi complètes y tout d\'un compris autre individu et dans élément fiables sur\nles\nMéthodologie de mise en œuvre - Approche en deux phases\nPhase 1 : Constitution de la Base de Données Nationale\nObjectif :\nprocédant Créer une à base la de données nationale solide à partir des documents\nrelatives numérisation des procès-verbaux (PV), plaintes et archives existants, en\ndemières aux années. infractions et activités criminelles établies au cours des cinq (5) policières à sept (7)\nÉtapes :\nCollecte systématique des archives physiques dans tous les\ncommissariats, sous-commissariats et unités de police.\nNumérisation et encodages de chaque élément constituant un dossier\n(infractions, identités, faits, lieux, dates, preuves, pièces jointes, etc.).\nOrganisation de ces informations dans un système centralisée,','succeeded',91.43,'2026-05-21 01:43:34'),(5,5,1,'fileAuthentiq/doc_5_p1_6a0e7ef40ed1b.jpg',386919,'SNIP\nLe Système Numérique d\'Information Policière (SNIP) est une initiative\nessentielles libes à la sécurité, au contrôle et au maintien de l\'ordre public par l\'État.\nconçue pour collecter, centraliser et transmettre instantanément des informations innovante\nSon objectif principal est de faciliter l\'accès à des données complètes et fiables\nconcernant les crimes, infractions, plaintes, contentieux, ainsi que tout autre élément\npermettant de retracer les antécédents criminels et infractionnels d\'un individu sur\nl\'ensemble du territoire de la République Démocratique du Congo, y compris dans les\nzones les plus reculées et difficile d\'accès.\nMéthodologie de mise en ceuvre - Approche en deux phases\nPhase 1 : Constitution de la Base de Données Nationale\nObjectif : base de données nationale solide à partir des documents archives existants, policières en\nCréer une numérisation des procès-verbaux (PV). plaintes et (5) à sept (7)\nprocédant relatives aux à la infractions et activités criminelles établies au cours des cinq\ndernières années.','succeeded',93.64,'2026-05-21 02:41:40'),(6,6,1,'fileAuthentiq/doc_6_p1_6a0eee156ecc2.jpg',145228,'ACCORD DE CONFIDENTIALITÉ\n- — informed - La societé \" du technology\nENTRE LES SOUSSIGNES\nMadama Innocente commune de Ngaliema, a Kimhasa on\nI ukuwa Mbemba nee a Kinshasa, la 11/03/1996 Republique résident sur l\'avense\nDemocratique Avenue memero du 8. Congu numbro de passeport OF1964009P d-après dénommine la . Partie\nDivelgatrice\nD\'UNE PART\nET\nDataxell ayant son siege au or niveau, Immenble Goll Appart. n°21, Boulevard\nLa unit commone de la Gombe. Kinihasa Republique Démocratique du Congo,\ndu 10 Jain au RCCM was le numtro CD/KNM/RCCM/20-A-01-722, identifiee sous to\nnuméro immatriculée ID NAT 01-G4-701-N71406N a le numéro d\'impôt A2321361U dûment\nrepresentee pur LON gerent. Mr Cauthier Ntudi,\nLT\nGauthier Ntudi ne 1 Kinshasa le 04/08/1990, résidant a 28. Avenue de La Pan.\nQuartier Mommer Baruti commune de Mont-Ngafula, Kinshasa Republique Démocratique du Congo,\nnuméro de passeport OP1769933\nci-apres conjectement a solidairement dénommés la \" Partie Receptrice a\nD\'AUTRE PART\nPRÉAMBULE\nDans le cadre des prestations de services informatiques confiées par la Partie Divulgatrice la\nPartia Receptrice vera amende à accéder à certaines informations présentant un caractere\ninclement confidentiel. notamment des données à caractère personnel telles que des numéros\nde telephone\nLes Parties revensament que ces informations sont sensibles et que toute divulgation ou\natilisation non autorisée est susceptible de causer un préjudice grave, tant à la Partie\nDivelgatrice qu\'aux personnes concernees\nEn consiquence. il a etc convenu ce qui suit','succeeded',76.10,'2026-05-21 10:35:49');
/*!40000 ALTER TABLE `ENCODAGES_PAGES` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2026_05_20_000001_add_encodage_verify_and_expired',1),(2,'2026_05_21_000001_add_textract_status_to_encodage_pages',2),(3,'0001_01_01_000001_create_cache_table',3),(4,'0001_01_01_000002_create_jobs_table',4),(5,'2026_05_21_000002_widen_quality_score_on_encodage_pages',5),(6,'0001_01_01_000000_create_users_table',6);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `OTP_CODES`
--

DROP TABLE IF EXISTS `OTP_CODES`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `OTP_CODES` (
  `id_otp` int NOT NULL AUTO_INCREMENT,
  `user_type` enum('user','client') NOT NULL,
  `user_id` int DEFAULT NULL,
  `client_id` int DEFAULT NULL,
  `code_otp` varchar(10) NOT NULL,
  `expire_at` datetime NOT NULL,
  `attempts` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_otp`),
  UNIQUE KEY `user_type` (`user_type`,`user_id`,`client_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `OTP_CODES`
--

LOCK TABLES `OTP_CODES` WRITE;
/*!40000 ALTER TABLE `OTP_CODES` DISABLE KEYS */;
INSERT INTO `OTP_CODES` VALUES (1,'client',NULL,1,'025202','2025-09-30 16:17:29',0,'2025-09-30 17:07:29'),(3,'client',NULL,3,'949312','2025-09-30 17:32:02',0,'2025-09-30 18:22:02'),(5,'client',NULL,5,'880501','2025-09-30 18:21:40',0,'2025-09-30 19:11:40'),(11,'client',NULL,2,'211152','2026-05-21 03:11:05',0,'2026-05-21 04:01:06');
/*!40000 ALTER TABLE `OTP_CODES` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `PROVINCES`
--

DROP TABLE IF EXISTS `PROVINCES`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PROVINCES` (
  `id_province` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  PRIMARY KEY (`id_province`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `PROVINCES`
--

LOCK TABLES `PROVINCES` WRITE;
/*!40000 ALTER TABLE `PROVINCES` DISABLE KEYS */;
INSERT INTO `PROVINCES` VALUES (1,'Kinshasa'),(2,'Kongo Central'),(3,'Kwango'),(4,'Kwilu'),(5,'Mai-Ndombe'),(6,'Sankuru'),(7,'Kasai'),(8,'Kasai-Central'),(9,'Kasai-Oriental'),(10,'Lomami'),(11,'Haut-Lomami'),(12,'Haut-Katanga'),(13,'Tanganyika'),(14,'Lualaba'),(15,'Tshuapa'),(16,'Equateur'),(17,'Mongala'),(18,'Nord-Ubangi'),(19,'Sud-Ubangi'),(20,'Tshopo'),(21,'Haut-Uele'),(22,'Bas-Uele'),(23,'Ituri'),(24,'Nord-Kivu'),(25,'Sud-Kivu'),(26,'Maniema');
/*!40000 ALTER TABLE `PROVINCES` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `REPORTS_LOG`
--

DROP TABLE IF EXISTS `REPORTS_LOG`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `REPORTS_LOG` (
  `id_report` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `type` enum('daily','monthly','global') DEFAULT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `generated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_report`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `REPORTS_LOG`
--

LOCK TABLES `REPORTS_LOG` WRITE;
/*!40000 ALTER TABLE `REPORTS_LOG` DISABLE KEYS */;
/*!40000 ALTER TABLE `REPORTS_LOG` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `USERS`
--

DROP TABLE IF EXISTS `USERS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `USERS` (
  `id_user` int NOT NULL AUTO_INCREMENT,
  `nom_complet` varchar(255) DEFAULT NULL,
  `tel` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `id_province` int DEFAULT NULL,
  `id_ville` int DEFAULT NULL,
  `affectation` varchar(255) DEFAULT NULL,
  `id_commune` int DEFAULT NULL,
  PRIMARY KEY (`id_user`),
  KEY `id_province` (`id_province`),
  KEY `id_ville` (`id_ville`),
  KEY `id_commune` (`id_commune`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`id_province`) REFERENCES `PROVINCES` (`id_province`),
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`id_ville`) REFERENCES `VILLES` (`id_ville`),
  CONSTRAINT `users_ibfk_3` FOREIGN KEY (`id_commune`) REFERENCES `COMMUNES` (`id_commune`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `USERS`
--

LOCK TABLES `USERS` WRITE;
/*!40000 ALTER TABLE `USERS` DISABLE KEYS */;
INSERT INTO `USERS` VALUES (1,'Gaella Mbongo','0815191631','gaellembongo.2018@gmail.com','$2y$10$EXlo/PsUzlOV2hjg8Rq9w.FZiOKbsPtE8S5wI07w59Q9lysHLfiC6','uploads/users/user_68db097c0e7e8.jpg','user',1,1,'Gombe',5),(2,'Albert Camus','0894435251','kipaobuildsarlu@gmail.com','$2y$10$nt5UTi2qrR316HCojduMdeq.HtscVE6XbzoWnWtClfv2V9BNFQeXi','uploads/users/user_68db1fa763cca.jpg','user',1,1,'Mont Ngafula',1),(4,'Gauthier NT','0824269291','gauthierntudi@gmail.com','$2y$12$iVhbbNh50wfNMROe6rNIjuS.QXWYWpnR6Lk9W1c5O5JCCUfcb35xG','uploads/users/user_MQS32zeX0Dfo.jpg','admin',1,1,'Gombe',5),(5,'Okito Mata David','0851208817','salondelemploirdc@gmail.com','$2y$10$nOUPT44YNKB.jOcaU1a4WOm2/MipHXLleN2AEQ32sIuXDexREirsS','uploads/users/user_68dc0e9fc922e.jpg','user',1,1,'N\'djili',2),(6,'Kurtis Makanda','0858658248','makurtis25@gmail.com','$2y$10$olC0H0MaLijPqOLaR..Pa.8CkuH11IFCyb4gwICnBF0wyC8.XcpWG','uploads/users/user_68e432579a921.jpg','user',2,4,'Nzanza',6),(7,'Yen O\'briahan','0810812331','almati.digital@gmail.com','$2y$12$bpYl9dTRQPV4b/OXrcz/GO1ns4xSkm2onP1KJmwMGuq/90neHhqim','uploads/users/user_TBOmCkoPG9FE.jpg','user',2,4,'Nzanza',6);
/*!40000 ALTER TABLE `USERS` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `VILLES`
--

DROP TABLE IF EXISTS `VILLES`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `VILLES` (
  `id_ville` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `id_province` int DEFAULT NULL,
  PRIMARY KEY (`id_ville`),
  KEY `id_province` (`id_province`),
  CONSTRAINT `villes_ibfk_1` FOREIGN KEY (`id_province`) REFERENCES `PROVINCES` (`id_province`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `VILLES`
--

LOCK TABLES `VILLES` WRITE;
/*!40000 ALTER TABLE `VILLES` DISABLE KEYS */;
INSERT INTO `VILLES` VALUES (1,'Kinshasa',1),(3,'Kolwezi',14),(4,'Matadi',2),(5,'Boma',2);
/*!40000 ALTER TABLE `VILLES` ENABLE KEYS */;
UNLOCK TABLES;
SET @@SESSION.SQL_LOG_BIN = @MYSQLDUMP_TEMP_LOG_BIN;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-21 16:59:09
