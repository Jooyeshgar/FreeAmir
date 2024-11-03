TRUNCATE TABLE `subjects`;

INSERT INTO `subjects` (
  `id`, `code`, `name`, `type`, `_lft`, `_rgt`, `parent_id`, `created_at`, `updated_at`
)
SELECT
  `id`,
  `code`,
  `name`,
  CASE 
    WHEN `type` = 0 THEN 'both'
    WHEN `type` = 1 THEN 'creditor'
    WHEN `type` = 2 THEN 'debtor'
  END AS `type`,
  `lft` AS `_lft`,
  `rgt` AS `_rgt`,
  `parent_id`,
  NOW(),
  NOW()
FROM `subject_old`;

DROP TABLE `subject_old`;

INSERT INTO `customers` (
  `id`, `code`, `name`, `subject_id`, `phone`, `cell`, `fax`, `address`, `postal_code`, 
  `email`, `ecnmcs_code`, `personal_code`, `web_page`, `responsible`, `connector`, 
  `group_id`, `desc`, `balance`, `credit`, `rep_via_email`, `acc_name_1`, `acc_no_1`, 
  `acc_bank_1`, `acc_name_2`, `acc_no_2`, `acc_bank_2`, `type_buyer`, `type_seller`, 
  `type_mate`, `type_agent`, `introducer_id`, `commission`, `marked`, `reason`, 
  `disc_rate`, `created_at`, `updated_at`
)
SELECT
  `custId`, `custCode`, `custName`, `custSubj`, COALESCE(`custPhone`, ''), 
  COALESCE(`custCell`, ''), COALESCE(`custFax`, ''), COALESCE(`custAddress`, ''), 
  COALESCE(`custPostalCode`, ''), COALESCE(`custEmail`, ''), COALESCE(`custEcnmcsCode`, ''), 
  COALESCE(`custPersonalCode`, ''), COALESCE(`custWebPage`, ''), COALESCE(`custResposible`, ''), 
  COALESCE(`custConnector`, ''), `custGroup`, `custDesc`, `custBalance`, `custCredit`, 
  `custRepViaEmail`, COALESCE(`custAccName1`, ''), COALESCE(`custAccNo1`, ''), 
  COALESCE(`custAccBank1`, ''), COALESCE(`custAccName2`, ''), COALESCE(`custAccNo2`, ''), 
  COALESCE(`custAccBank2`, ''), `custTypeBuyer`, `custTypeSeller`, `custTypeMate`, 
  `custTypeAgent`, `custIntroducer`, `custCommission`, `custMarked`, 
  COALESCE(`custReason`, ''), `custDiscRate`, NOW(), NOW()
FROM `customers_old`;

DROP TABLE `customers_old`;

INSERT INTO `documents` (`id`, `number`, `date`, `approved_at`, `company_id`, `creator_id`, `created_at`, `updated_at`)
SELECT 
  `id`, 
  `number`, 
  `date`, 
  `date`,  -- Use `date` for `approved_at`
  1,       -- Use 1 for `company_id`
  1,       -- Use 1 for `creator_id`
  `creation_date` AS `created_at`,
  `lastedit_date` AS `updated_at`
FROM `bill_old`;

DROP TABLE `bill_old`;


INSERT INTO `transactions` (`id`, `subject_id`, `document_id`, `user_id`, `desc`, `value`, `created_at`, `updated_at`)
SELECT 
  `id`,
  `subject_id`,
  `bill_id` AS `document_id`,
  1 AS `user_id`, -- Use 1 for `user_id`
  `desc`,
  `value`,
  NOW() AS `created_at`,  -- Set current timestamp for `created_at`
  NOW() AS `updated_at`   -- Set current timestamp for `updated_at`
FROM `notebook_old`;

DROP TABLE `notebook_old`;


DROP TABLE IF EXISTS `bankAccounts_old`;
DROP TABLE IF EXISTS `BankNames_old`;
DROP TABLE IF EXISTS `ChequeHistory_old`;
DROP TABLE IF EXISTS `Cheque_old`;
DROP TABLE IF EXISTS `config_old`;
DROP TABLE IF EXISTS `custGroups_old`;
DROP TABLE IF EXISTS `exchanges_old`;
DROP TABLE IF EXISTS `factorItems_old`;
DROP TABLE IF EXISTS `factors_old`;
DROP TABLE IF EXISTS `migrate_version_old`;
DROP TABLE IF EXISTS `payment_old`;
DROP TABLE IF EXISTS `productGroups_old`;
DROP TABLE IF EXISTS `products_old`;
DROP TABLE IF EXISTS `transactions_old`;
DROP TABLE IF EXISTS `users_old`;