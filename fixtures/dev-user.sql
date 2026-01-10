DELETE FROM user;
INSERT INTO user (username, email, password, totp_secret, theme, created_at)
VALUES (
    'dev@localhost.arpa',
    'dev@localhost.arpa',
    '$2y$13$XsZhAfRBWw77xpySL0g5JeKJtA.ATpZxcnLNM48dgd/EsEDlcmU3O',
    '3DDQUI6BMJAJMWV3U5YGHSZYKVCHZIUAQCTI6ZWWEHYNI5JSLCYZ75ADRJQQC3BECC73O2GWOSWGO6MLRD56MONJXPOF23NIA47TLLQ',
    'auto',
    datetime('now')
);
