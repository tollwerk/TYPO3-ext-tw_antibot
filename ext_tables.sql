#
# Table structure for table 'tx_twantibot_domain_model_blacklist'
#
CREATE TABLE tx_twantibot_domain_model_blacklist
(

  property int(11)      DEFAULT '0' NOT NULL,
  value    varchar(255) DEFAULT ''  NOT NULL,
  data     text,
  error    text,

);

#
# Table structure for table 'tx_twantibot_domain_model_whitelist'
#
CREATE TABLE tx_twantibot_domain_model_whitelist
(

  property int(11)      DEFAULT '0' NOT NULL,
  value    varchar(255) DEFAULT ''  NOT NULL,
  note     text,

);
