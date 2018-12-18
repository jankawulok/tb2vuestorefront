CREATE TABLE IF NOT EXISTS `PREFIX_tb2vuestorefront_index_status` (
  `id_tb2vuestorefront_index_status`  INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_entity`                         INT(11) UNSIGNED NOT NULL,
  `index`                             VARCHAR(255)     NOT NULL,
  `id_shop`                           INT(11) UNSIGNED NOT NULL,
  `id_lang`                           INT(11) UNSIGNED NOT NULL,
  `date_upd`                          DATETIME         NOT NULL,
  `error`                             TEXT             DEFAULT NULL,
  PRIMARY KEY (`id_tb2vuestorefront_index_status`),
  UNIQUE (`id_entity`, `index`, `id_shop`, `id_lang`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `PREFIX_tb2vuestorefront_entity_type` (
  `id_tb2vuestorefront_entity_type` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_name`                      VARCHAR(255),
  `entity_name`                     VARCHAR(255),
  `enabled`                         TINYINT(1) UNSIGNED NOT NULL DEFAULT '1',
  `id_shop`                         INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`id_tb2vuestorefront_entity_type`),
  UNIQUE (`class_name`, `id_shop`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_tb2vuestorefront_meta` (
  `id_tb2vuestorefront_meta`  INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `alias`                     VARCHAR(190)        NOT NULL,
  `code`                      VARCHAR(190)        NOT NULL,
  `enabled`                   TINYINT(1) UNSIGNED NOT NULL DEFAULT '1',
  `meta_type`                 VARCHAR(255)        NOT NULL DEFAULT 'attribute',
  `elastic_type`              VARCHAR(255)        NOT NULL DEFAULT 'text',
  `searchable`                TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  `weight`                    FLOAT               NOT NULL DEFAULT '1.00000',
  `position`                  INT(11) UNSIGNED    NOT NULL,
  `aggregatable`              TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  `operator`                  TINYINT(1) UNSIGNED NOT NULL DEFAULT '1',
  `display_type`              INT(11) UNSIGNED    NOT NULL DEFAULT '1',
  `result_limit`              INT(11) UNSIGNED    NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_tb2vuestorefront_meta`),
  UNIQUE (`alias`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_tb2vuestorefront_meta_lang` (
  `id_tb2vuestorefront_meta`  INT(11) UNSIGNED NOT NULL,
  `id_lang`                   INT(11) UNSIGNED NOT NULL,
  `name`                      VARCHAR(255)     NOT NULL,
  PRIMARY KEY (`id_tb2vuestorefront_meta`, `id_lang`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
