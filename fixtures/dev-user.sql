DELETE FROM user;
INSERT INTO user (username, email, password, totp_secret, theme, created_at)
VALUES (
    'dev@localhost.arpa',
    'dev@localhost.arpa',
    '$2y$13$8wsRpG.JyrTHB1ORc63lMOjNF58JrbnuwYj5h919Qv8Oe6OvzXI7y',
    '6i4IwE1zqA0kW447uWm2Anq+vap+Utck3tR+dmgCriCCVKXRZSvHO2SBqGlcHuV+XFq4y+q/wK8OH8KU8CfGEmpKpr+5yKcCxhHkzoRAsi3YRWdPJ1cX/NeZzy0iNjwoUjVCtjHNWPjAOOQLML/4iAUCK7HWj7qhSYdGj8l63WLzTSaM0vnN/cxvs19rfFo=',
    'auto',
    datetime('now')
);
