DELETE FROM user;
INSERT INTO user (username, email, password, totp_secret, created_at)
VALUES (
    'Tender Pony',
    'dev@localhost.arpa',
    '$2y$13$wLK50jSqOqNwHG8SWYYqh.VfrxsJLD/NI.agrtYrkhgNDGhOEeTse',
    'pKqGj+bwzk4sxxMMSYat3bvZumega6ifhKvI3R27O6ha1ZXnXWHJYYY5WJy/4m+eoRQWnfBweiwb0fleTPPCJ0Pz0pxA9ofS3J+UIDZGeOMUzfJz/xcs2deNQISx5TpseyadHMaFuHIgaW2ZsHb0y/HL6jgyQi/xckbOBNodIJHt1Hp8AAumvgjYsGQhJno=',
    datetime('now')
);
