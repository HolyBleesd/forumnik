SET NAMES utf8mb4;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(30) UNIQUE NOT NULL,
    email VARCHAR(120) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    bio VARCHAR(255) DEFAULT '',
    role ENUM('user','moderator','admin') DEFAULT 'user',
    is_banned TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT '',
    icon VARCHAR(10) DEFAULT '💬',
    color VARCHAR(20) DEFAULT '#667eea',
    sort_order INT DEFAULT 0,
    is_hidden TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    views INT DEFAULT 0,
    is_pinned TINYINT(1) DEFAULT 0,
    is_locked TINYINT(1) DEFAULT 0,
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_cat (category_id),
    INDEX idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_topic (topic_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    post_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_like (user_id, post_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(20) NOT NULL,
    message VARCHAR(255) NOT NULL,
    link VARCHAR(255) DEFAULT '',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Категорії
INSERT INTO categories (name, description, icon, color, sort_order) VALUES
('Загальне',      'Загальні обговорення',              '💬', '#667eea', 1),
('Технології',    'IT, програмування, гаджети',         '💻', '#4facfe', 2),
('Ігри',          'Ігрові новини та обговорення',      '🎮', '#fa709a', 3),
('Музика',        'Все про музику',                     '🎵', '#43e97b', 4),
('Офтоп',         'Все, що завгодно',                   '🎲', '#f6d365', 5);

-- Адмін: логін admin, пароль admin123
INSERT INTO users (username, email, password, role) VALUES 
('admin', 'admin@forumhub.local',
'$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HlCS4bZJ18JuywdBmVvBmn8.xGyGGa', 'admin');

-- Приклад теми
INSERT INTO topics (category_id, user_id, title, content) VALUES
(1, 1, 'Ласкаво просимо на ForumHub!', 'Вітаємо на нашому форумі! Тут ви можете спілкуватися, ділитися думками та знаходити однодумців. Не забудьте представитися в цій темі 👋');

INSERT INTO posts (topic_id, user_id, content) VALUES
(1, 1, 'Правила форуму: 1. Поважайте інших. 2. Без спаму. 3. Без образ. Приємного спілкування!');