# The columns TYPO3 does not create from the TCA, and one record per subject
CREATE TABLE tx_twantibot_domain_model_blacklist
(
    property int(11)      DEFAULT '0' NOT NULL,
    value    varchar(255) DEFAULT ''  NOT NULL,
    data     mediumtext,
    error    text,
    UNIQUE KEY entry (value, property)
);
