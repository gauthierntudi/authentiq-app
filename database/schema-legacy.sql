-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:8889
-- Généré le : mer. 20 mai 2026 à 21:52
-- Version du serveur : 5.7.39
-- Version de PHP : 8.1.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Base de données : `authentiq.db`
--

-- --------------------------------------------------------

--
-- Structure de la table `CLIENTS`
--

CREATE TABLE `CLIENTS` (
  `id_client` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `nom_complet` varchar(255) DEFAULT NULL,
  `tel` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `id_province` int(11) DEFAULT NULL,
  `id_ville` int(11) DEFAULT NULL,
  `adresse` text,
  `type_piece_identite` varchar(50) DEFAULT NULL,
  `numero_national` varchar(50) DEFAULT NULL,
  `numero_passeport` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `CLIENTS`
--

INSERT INTO `CLIENTS` (`id_client`, `is_active`, `nom_complet`, `tel`, `email`, `photo`, `id_province`, `id_ville`, `adresse`, `type_piece_identite`, `numero_national`, `numero_passeport`, `created_at`) VALUES
(2, 0, 'Okito Mata David', '0851208817', 'salondelemploirdc@gmail.com', NULL, 1, 1, NULL, 'CNI', '8893652', '99837565', '2025-09-30 19:35:32'),
(4, 1, 'Albert Camus', '0846516270', 'kipaobuildsarlu@gmail.com', NULL, 1, 1, '28 De la paix Cité maman Mobutu, Q/dimese Mont Ngafula', 'CNI', '8893611', '99837599', '2025-09-30 19:35:32'),
(7, 0, 'Gauthier Ntudi', '0824269291', 'gauthierntudi@gmail.com', NULL, 1, 1, NULL, 'Passeport', '8893666', '96737577', '2025-09-30 19:35:32'),
(8, 1, 'Yen Docs', '0824444442', '', NULL, NULL, NULL, NULL, 'Passeport', '992773653', NULL, '2025-11-21 15:33:08');

-- --------------------------------------------------------

--
-- Structure de la table `COMMUNES`
--

CREATE TABLE `COMMUNES` (
  `id_commune` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `id_ville` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `COMMUNES`
--

INSERT INTO `COMMUNES` (`id_commune`, `nom`, `id_ville`) VALUES
(1, 'Mont Ngafula', 1),
(2, 'N\'djili', 1),
(3, 'Matete', 1),
(4, 'Limete', 1),
(5, 'Gombe', 1),
(6, 'Nzanza', 4),
(7, 'Sokol', 5);

-- --------------------------------------------------------

--
-- Structure de la table `DOCS`
--

CREATE TABLE `DOCS` (
  `id_doc` int(11) NOT NULL,
  `nom_doc` varchar(255) DEFAULT NULL,
  `type_doc` enum('free','payant') DEFAULT NULL,
  `montant` decimal(10,2) DEFAULT NULL,
  `duree` int(11) DEFAULT '0' COMMENT '0 = durée illimitée, sinon durée en mois',
  `validite` enum('court','moyen','long') DEFAULT 'court'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `DOCS`
--

INSERT INTO `DOCS` (`id_doc`, `nom_doc`, `type_doc`, `montant`, `duree`, `validite`) VALUES
(1, 'Attestation de Naissance', 'free', '0.00', 6, 'court'),
(2, 'Acte de Mariage', 'payant', '2.00', 0, 'long'),
(3, 'Attestation de perte de pièces', 'payant', '3.00', 12, 'court'),
(4, 'Livret Parcellaire', 'payant', '20.00', 0, 'long'),
(5, 'Permis de construire', 'payant', '5.00', 60, 'moyen');

-- --------------------------------------------------------

--
-- Structure de la table `ENCODAGES`
--

CREATE TABLE `ENCODAGES` (
  `id_encodage` int(11) NOT NULL,
  `id_doc` int(11) DEFAULT NULL,
  `id_client` int(11) DEFAULT NULL,
  `id_user` int(11) DEFAULT NULL,
  `type_doc` varchar(50) DEFAULT NULL,
  `montant` decimal(10,2) DEFAULT NULL,
  `id_province` int(11) DEFAULT NULL,
  `id_ville` int(11) DEFAULT NULL,
  `affectation` varchar(255) DEFAULT NULL,
  `files` text,
  `ocrTextFiles` text,
  `date_emission` date DEFAULT NULL,
  `date_expiration` date DEFAULT NULL,
  `id_commune` int(11) DEFAULT NULL,
  `status` enum('incomplete','complete') DEFAULT 'incomplete',
  `page_count` int(11) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `ENCODAGES`
--

INSERT INTO `ENCODAGES` (`id_encodage`, `id_doc`, `id_client`, `id_user`, `type_doc`, `montant`, `id_province`, `id_ville`, `affectation`, `files`, `ocrTextFiles`, `date_emission`, `date_expiration`, `id_commune`, `status`, `page_count`, `created_at`, `updated_at`) VALUES
(1, NULL, 8, 1, NULL, NULL, 1, 1, 'Gombe', '../uploads/fileAuthentiq/doc_1_p1_6920861ae651f.jpg', 'Fi\n|\n\\\n|\n| >=\n@ . 2\n+ Rs &\nvis 3\nE> J he a\n', NULL, NULL, 5, 'incomplete', 1, '2025-11-21 15:32:42', '2025-11-21 15:33:08');

-- --------------------------------------------------------

--
-- Structure de la table `ENCODAGES_PAGES`
--

CREATE TABLE `ENCODAGES_PAGES` (
  `id_page` int(11) NOT NULL,
  `id_encodage` int(11) NOT NULL,
  `page_number` int(11) NOT NULL COMMENT 'Numéro de la page (1, 2, 3...)',
  `file_path` varchar(500) NOT NULL COMMENT 'Chemin du fichier scanné',
  `file_size` int(11) DEFAULT NULL COMMENT 'Taille du fichier en bytes',
  `ocr_text` text COMMENT 'Texte OCR de cette page spécifique',
  `quality_score` decimal(3,2) DEFAULT NULL COMMENT 'Score de qualité du scan (0-100)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Pages scannées pour chaque encodage';

--
-- Déchargement des données de la table `ENCODAGES_PAGES`
--

INSERT INTO `ENCODAGES_PAGES` (`id_page`, `id_encodage`, `page_number`, `file_path`, `file_size`, `ocr_text`, `quality_score`, `created_at`) VALUES
(1, 1, 1, '../uploads/fileAuthentiq/doc_1_p1_6920861ae651f.jpg', 495324, 'Fi\n|\n\\\n|\n| >=\n@ . 2\n+ Rs &\nvis 3\nE> J he a\n', NULL, '2025-11-21 15:32:42');

-- --------------------------------------------------------

--
-- Structure de la table `OTP_CODES`
--

CREATE TABLE `OTP_CODES` (
  `id_otp` int(11) NOT NULL,
  `user_type` enum('user','client') NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `code_otp` varchar(10) NOT NULL,
  `expire_at` datetime NOT NULL,
  `attempts` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `OTP_CODES`
--

INSERT INTO `OTP_CODES` (`id_otp`, `user_type`, `user_id`, `client_id`, `code_otp`, `expire_at`, `attempts`, `created_at`) VALUES
(1, 'client', NULL, 1, '025202', '2025-09-30 16:17:29', 0, '2025-09-30 17:07:29'),
(2, 'client', NULL, 2, '755177', '2025-09-30 16:33:18', 0, '2025-09-30 17:23:18'),
(3, 'client', NULL, 3, '949312', '2025-09-30 17:32:02', 0, '2025-09-30 18:22:02'),
(5, 'client', NULL, 5, '880501', '2025-09-30 18:21:40', 0, '2025-09-30 19:11:40');

-- --------------------------------------------------------

--
-- Structure de la table `PROVINCES`
--

CREATE TABLE `PROVINCES` (
  `id_province` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `PROVINCES`
--

INSERT INTO `PROVINCES` (`id_province`, `nom`) VALUES
(1, 'Kinshasa'),
(2, 'Kongo Central'),
(3, 'Kwango'),
(4, 'Kwilu'),
(5, 'Mai-Ndombe'),
(6, 'Sankuru'),
(7, 'Kasai'),
(8, 'Kasai-Central'),
(9, 'Kasai-Oriental'),
(10, 'Lomami'),
(11, 'Haut-Lomami'),
(12, 'Haut-Katanga'),
(13, 'Tanganyika'),
(14, 'Lualaba'),
(15, 'Tshuapa'),
(16, 'Equateur'),
(17, 'Mongala'),
(18, 'Nord-Ubangi'),
(19, 'Sud-Ubangi'),
(20, 'Tshopo'),
(21, 'Haut-Uele'),
(22, 'Bas-Uele'),
(23, 'Ituri'),
(24, 'Nord-Kivu'),
(25, 'Sud-Kivu'),
(26, 'Maniema');

-- --------------------------------------------------------

--
-- Structure de la table `REPORTS_LOG`
--

CREATE TABLE `REPORTS_LOG` (
  `id_report` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('daily','monthly','global') DEFAULT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `generated_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `USERS`
--

CREATE TABLE `USERS` (
  `id_user` int(11) NOT NULL,
  `nom_complet` varchar(255) DEFAULT NULL,
  `tel` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `id_province` int(11) DEFAULT NULL,
  `id_ville` int(11) DEFAULT NULL,
  `affectation` varchar(255) DEFAULT NULL,
  `id_commune` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `USERS`
--

INSERT INTO `USERS` (`id_user`, `nom_complet`, `tel`, `email`, `password`, `photo`, `role`, `id_province`, `id_ville`, `affectation`, `id_commune`) VALUES
(1, 'Gaella Masisa', '0815191631', 'gaellembongo.2018@gmail.com', '$2y$10$EXlo/PsUzlOV2hjg8Rq9w.FZiOKbsPtE8S5wI07w59Q9lysHLfiC6', 'uploads/users/user_68db097c0e7e8.jpg', 'user', 1, 1, 'Gombe', 5),
(2, 'Albert Camus', '0824269291', 'kipaobuildsarlu@gmail.com', '$2y$10$nt5UTi2qrR316HCojduMdeq.HtscVE6XbzoWnWtClfv2V9BNFQeXi', 'uploads/users/user_68db1fa763cca.jpg', 'user', 1, 1, 'Mont Ngafula', 1),
(4, 'Gauthier Ntudi', '0846516270', 'gauthierntudi@gmail.com', '$2y$10$w64JTRgrPOkHNt/tfEgeVeL.55PAolC6LWRprZUYlWJREpmtYxa4W', '../uploads/users/user_68e06e2195a68.jpg', 'user', 1, 1, 'Gombe', 5),
(5, 'Okito Mata David', '0851208817', 'salondelemploirdc@gmail.com', '$2y$10$nOUPT44YNKB.jOcaU1a4WOm2/MipHXLleN2AEQ32sIuXDexREirsS', 'uploads/users/user_68dc0e9fc922e.jpg', 'user', 1, 1, 'N\'djili', 2),
(6, 'Kurtis Makanda', '0858658248', 'makurtis25@gmail.com', '$2y$10$olC0H0MaLijPqOLaR..Pa.8CkuH11IFCyb4gwICnBF0wyC8.XcpWG', 'uploads/users/user_68e432579a921.jpg', 'user', 2, 4, 'Nzanza', 6);

-- --------------------------------------------------------

--
-- Structure de la table `VILLES`
--

CREATE TABLE `VILLES` (
  `id_ville` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `id_province` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `VILLES`
--

INSERT INTO `VILLES` (`id_ville`, `nom`, `id_province`) VALUES
(1, 'Kinshasa', 1),
(3, 'Kolwezi', 14),
(4, 'Matadi', 2),
(5, 'Boma', 2);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `CLIENTS`
--
ALTER TABLE `CLIENTS`
  ADD PRIMARY KEY (`id_client`),
  ADD KEY `id_province` (`id_province`),
  ADD KEY `clients_ibfk_2` (`id_ville`);

--
-- Index pour la table `COMMUNES`
--
ALTER TABLE `COMMUNES`
  ADD PRIMARY KEY (`id_commune`),
  ADD KEY `id_ville` (`id_ville`);

--
-- Index pour la table `DOCS`
--
ALTER TABLE `DOCS`
  ADD PRIMARY KEY (`id_doc`);

--
-- Index pour la table `ENCODAGES`
--
ALTER TABLE `ENCODAGES`
  ADD PRIMARY KEY (`id_encodage`),
  ADD KEY `id_doc` (`id_doc`),
  ADD KEY `id_client` (`id_client`),
  ADD KEY `id_province` (`id_province`),
  ADD KEY `id_ville` (`id_ville`),
  ADD KEY `id_commune` (`id_commune`),
  ADD KEY `idx_encodages_status` (`status`),
  ADD KEY `idx_encodages_user_status` (`id_user`,`status`),
  ADD KEY `idx_encodages_created` (`created_at`),
  ADD KEY `idx_encodages_user_commune_status` (`id_user`,`id_commune`,`status`);

--
-- Index pour la table `ENCODAGES_PAGES`
--
ALTER TABLE `ENCODAGES_PAGES`
  ADD PRIMARY KEY (`id_page`),
  ADD KEY `idx_encodage` (`id_encodage`),
  ADD KEY `idx_page_number` (`page_number`);

--
-- Index pour la table `OTP_CODES`
--
ALTER TABLE `OTP_CODES`
  ADD PRIMARY KEY (`id_otp`),
  ADD UNIQUE KEY `user_type` (`user_type`,`user_id`,`client_id`,`created_at`);

--
-- Index pour la table `PROVINCES`
--
ALTER TABLE `PROVINCES`
  ADD PRIMARY KEY (`id_province`);

--
-- Index pour la table `REPORTS_LOG`
--
ALTER TABLE `REPORTS_LOG`
  ADD PRIMARY KEY (`id_report`);

--
-- Index pour la table `USERS`
--
ALTER TABLE `USERS`
  ADD PRIMARY KEY (`id_user`),
  ADD KEY `id_province` (`id_province`),
  ADD KEY `id_ville` (`id_ville`),
  ADD KEY `id_commune` (`id_commune`);

--
-- Index pour la table `VILLES`
--
ALTER TABLE `VILLES`
  ADD PRIMARY KEY (`id_ville`),
  ADD KEY `id_province` (`id_province`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `CLIENTS`
--
ALTER TABLE `CLIENTS`
  MODIFY `id_client` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `COMMUNES`
--
ALTER TABLE `COMMUNES`
  MODIFY `id_commune` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `DOCS`
--
ALTER TABLE `DOCS`
  MODIFY `id_doc` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `ENCODAGES`
--
ALTER TABLE `ENCODAGES`
  MODIFY `id_encodage` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `ENCODAGES_PAGES`
--
ALTER TABLE `ENCODAGES_PAGES`
  MODIFY `id_page` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `OTP_CODES`
--
ALTER TABLE `OTP_CODES`
  MODIFY `id_otp` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `PROVINCES`
--
ALTER TABLE `PROVINCES`
  MODIFY `id_province` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT pour la table `REPORTS_LOG`
--
ALTER TABLE `REPORTS_LOG`
  MODIFY `id_report` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `USERS`
--
ALTER TABLE `USERS`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `VILLES`
--
ALTER TABLE `VILLES`
  MODIFY `id_ville` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `CLIENTS`
--
ALTER TABLE `CLIENTS`
  ADD CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`id_province`) REFERENCES `PROVINCES` (`id_province`),
  ADD CONSTRAINT `clients_ibfk_2` FOREIGN KEY (`id_ville`) REFERENCES `VILLES` (`id_ville`);

--
-- Contraintes pour la table `COMMUNES`
--
ALTER TABLE `COMMUNES`
  ADD CONSTRAINT `communes_ibfk_1` FOREIGN KEY (`id_ville`) REFERENCES `VILLES` (`id_ville`);

--
-- Contraintes pour la table `ENCODAGES`
--
ALTER TABLE `ENCODAGES`
  ADD CONSTRAINT `encodages_ibfk_1` FOREIGN KEY (`id_doc`) REFERENCES `DOCS` (`id_doc`),
  ADD CONSTRAINT `encodages_ibfk_2` FOREIGN KEY (`id_client`) REFERENCES `CLIENTS` (`id_client`),
  ADD CONSTRAINT `encodages_ibfk_3` FOREIGN KEY (`id_user`) REFERENCES `USERS` (`id_user`),
  ADD CONSTRAINT `encodages_ibfk_4` FOREIGN KEY (`id_province`) REFERENCES `PROVINCES` (`id_province`),
  ADD CONSTRAINT `encodages_ibfk_5` FOREIGN KEY (`id_ville`) REFERENCES `VILLES` (`id_ville`),
  ADD CONSTRAINT `encodages_ibfk_6` FOREIGN KEY (`id_commune`) REFERENCES `COMMUNES` (`id_commune`);

--
-- Contraintes pour la table `ENCODAGES_PAGES`
--
ALTER TABLE `ENCODAGES_PAGES`
  ADD CONSTRAINT `fk_pages_encodage` FOREIGN KEY (`id_encodage`) REFERENCES `ENCODAGES` (`id_encodage`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `USERS`
--
ALTER TABLE `USERS`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`id_province`) REFERENCES `PROVINCES` (`id_province`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`id_ville`) REFERENCES `VILLES` (`id_ville`),
  ADD CONSTRAINT `users_ibfk_3` FOREIGN KEY (`id_commune`) REFERENCES `COMMUNES` (`id_commune`);

--
-- Contraintes pour la table `VILLES`
--
ALTER TABLE `VILLES`
  ADD CONSTRAINT `villes_ibfk_1` FOREIGN KEY (`id_province`) REFERENCES `PROVINCES` (`id_province`);
COMMIT;
