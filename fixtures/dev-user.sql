DELETE FROM user;
INSERT INTO user (username, email, password, totp_secret, created_at)
VALUES (
    'dev@localhost.arpa',
    'dev@localhost.arpa',
    '$2y$13$LjFHqJbDMLa9ilMaPf8lMOljUOc87tiXBQ3a16tLm6kA5skCyuAoC',
    '6Q0VHyiP1F5li7Ahb9q1b9Gosp3ZUAa0xrgn+3ra/M+EQ9dU7+7zOEAi20J4m9HJUd4RrlwPWgaQ7HfCTgjfGp7MHAsKOBPrMXrWW8VcoYoS8HFQhVmSLW/yLBZAjvwsOLg0aANdsqgltCZRdzRTjbiH8E5fROuP9SjVXVzYNeIRhxeAVh1s9+9+PTtejUg=',
    datetime('now')
);
