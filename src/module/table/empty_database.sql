Drop table if exists `gamelog`;
Drop table if exists `global`;
Drop table if exists `player`;
Drop table if exists `replay`;
Drop table if exists `stats`;

Drop table if exists `energy`;
Drop table if exists `plenitude`;
Drop table if exists `inspiration`;
Drop table if exists `card`;
Drop table if exists `task`;
Drop table if exists `logbook`;

--
-- Structure de la table `gamelog`
--

CREATE TABLE `gamelog` (
  `gamelog_id` int(10) UNSIGNED NOT NULL,
  `gamelog_move_id` int(10) UNSIGNED DEFAULT NULL,
  `gamelog_private` tinyint(1) NOT NULL,
  `gamelog_time` datetime NOT NULL,
  `gamelog_player` int(10) UNSIGNED DEFAULT NULL COMMENT 'null if main channel',
  `gamelog_current_player` int(10) UNSIGNED DEFAULT NULL COMMENT 'player that sent the request that leads to this notif',
  `gamelog_notification` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


--
-- Structure de la table `global`
--

CREATE TABLE `global` (
  `global_id` int(10) UNSIGNED NOT NULL,
  `global_value` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `player`
--

CREATE TABLE `player` (
  `player_no` int(10) UNSIGNED NOT NULL,
  `player_id` int(10) UNSIGNED NOT NULL COMMENT 'Reference to metagame player id',
  `player_canal` varchar(32) NOT NULL COMMENT 'Player comet d "secret" canal',
  `player_name` varchar(32) NOT NULL,
  `player_avatar` varchar(10) NOT NULL,
  `player_color` varchar(6) NOT NULL,
  `player_score` int(10) NOT NULL DEFAULT '0',
  `player_score_aux` int(10) NOT NULL DEFAULT '0',
  `player_zombie` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = player is a zombie',
  `player_eliminated` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = player has been eliminated',
  `player_next_notif_no` int(10) UNSIGNED NOT NULL DEFAULT '1' COMMENT 'Next notification no to be sent to player',
  `player_enter_game` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = player load game view at least once',
  `player_over_time` tinyint(1) NOT NULL DEFAULT '0',
  `player_is_multiactive` tinyint(1) NOT NULL DEFAULT '0',
  `player_start_reflexion_time` datetime DEFAULT NULL COMMENT 'Time when the player reflexion time starts. NULL if its not this player turn',
  `player_remaining_reflexion_time` int(11) DEFAULT NULL COMMENT 'Remaining reflexion time. This does not include reflexion time for current move.',
  `player_beginner` varbinary(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Contenu de la table `player`
--

-- --------------------------------------------------------

--
-- Structure de la table `replay`
--

CREATE TABLE `replay` (
  `replay_move_id` int(10) UNSIGNED NOT NULL,
  `replay_player_id` int(10) UNSIGNED NOT NULL,
  `replay_gamedatas` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Structure de la table `stats`
--

CREATE TABLE `stats` (
  `stats_id` int(10) UNSIGNED NOT NULL,
  `stats_type` smallint(5) UNSIGNED NOT NULL,
  `stats_player_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'if NULL: stat global to table',
  `stats_value` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Contenu de la table `stats`
--


--
-- Index pour les tables exportées
--

--
-- Index pour la table `gamelog`
--
ALTER TABLE `gamelog`
  ADD PRIMARY KEY (`gamelog_id`);

--
-- Index pour la table `global`
--
ALTER TABLE `global`
  ADD PRIMARY KEY (`global_id`);

--
-- Index pour la table `player`
--
ALTER TABLE `player`
  ADD PRIMARY KEY (`player_no`),
  ADD UNIQUE KEY `player_id` (`player_id`);

--
-- Index pour la table `replaysavepoint`
--
ALTER TABLE `replay`
  ADD PRIMARY KEY (`replay_move_id`,`replay_player_id`);

--
-- Index pour la table `stats`
--
ALTER TABLE `stats`
  ADD PRIMARY KEY (`stats_id`),
  ADD UNIQUE KEY `stats_table_id` (`stats_type`,`stats_player_id`),
  ADD KEY `stats_player_id` (`stats_player_id`);

--
-- AUTO_INCREMENT pour les tables exportées
--

--
-- AUTO_INCREMENT pour la table `gamelog`
--
ALTER TABLE `gamelog`
  MODIFY `gamelog_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
--
-- AUTO_INCREMENT pour la table `player`
--
ALTER TABLE `player`
  MODIFY `player_no` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
--
-- AUTO_INCREMENT pour la table `stats`
--
ALTER TABLE `stats`
  MODIFY `stats_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
  
  
  COMMIT;
