-- Base de datos de producción/desarrollo
CREATE DATABASE IF NOT EXISTS `control_g`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Base de datos de testing (aislada)
CREATE DATABASE IF NOT EXISTS `control_g_testing`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON `control_g`.* TO 'controlguser'@'%';
GRANT ALL PRIVILEGES ON `control_g_testing`.* TO 'controlguser'@'%';
FLUSH PRIVILEGES;
