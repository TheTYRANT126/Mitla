<?php


class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    

    public function login($email, $password) {

        $usuario = $this->db->fetchOne(
            "SELECT * FROM usuarios WHERE email = ? AND activo = 1",
            [$email]
        );
        
        if (!$usuario) {
            return [
                'success' => false,
                'message' => 'Credenciales incorrectas'
            ];
        }
        
        // Verificar contraseña
        if (!password_verify($password, $usuario['password_hash'])) {
            // Registrar intento fallido
            $this->registrarIntentoFallido($email);
            
            return [
                'success' => false,
                'message' => 'Credenciales incorrectas'
            ];
        }
        
        // Crear sesión
        $_SESSION['user_id'] = $usuario['id_usuario'];
        $_SESSION['user_role'] = $usuario['rol'];
        $_SESSION['user_name'] = $usuario['nombre_completo'];
        $_SESSION['last_activity'] = time();
        
        // Registrar login exitoso
        $this->registrarLogin($usuario['id_usuario']);
        
        return [
            'success' => true,
            'role' => $usuario['rol'],
            'redirect' => $usuario['rol'] === 'admin' ? '/admin/dashboard.php' : '/admin/guia/mis-tours.php'
        ];
    }
    
    /**
     * Cerrar sesión
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            logActivity("Usuario cerró sesión", 'info');
        }
        
        session_destroy();
        return true;
    }
    
    /**
     * Verificar si el usuario está autenticado
     */
    public function isAuthenticated() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Verificar timeout de sesión
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
            $this->logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Verificar si el usuario es administrador
     */
    public function isAdmin() {
        return $this->isAuthenticated() && $_SESSION['user_role'] === 'admin';
    }
    
    /**
     * Verificar si el usuario es guía
     */
    public function isGuia() {
        return $this->isAuthenticated() && $_SESSION['user_role'] === 'guia';
    }
    
    /**
     * Obtener ID del usuario actual
     */
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Registrar intento de login fallido
     */
    private function registrarIntentoFallido($email) {
        $this->db->execute(
            "INSERT INTO intentos_login (email, ip, fecha_intento) VALUES (?, ?, NOW())",
            [$email, $_SERVER['REMOTE_ADDR']]
        );
    }
    
    /**
     * Registrar login exitoso
     */
    private function registrarLogin($userId) {
        $this->db->execute(
            "UPDATE usuarios SET ultimo_login = NOW() WHERE id_usuario = ?",
            [$userId]
        );
        
        logActivity("Inicio de sesión exitoso", 'info');
    }
    
    /**
     * Cambiar contraseña
     */
    public function cambiarPassword($userId, $passwordActual, $passwordNueva) {
        $usuario = $this->db->fetchOne(
            "SELECT password_hash FROM usuarios WHERE id_usuario = ?",
            [$userId]
        );
        
        if (!password_verify($passwordActual, $usuario['password_hash'])) {
            return [
                'success' => false,
                'message' => 'La contraseña actual es incorrecta'
            ];
        }
        
        $newHash = password_hash($passwordNueva, PASSWORD_BCRYPT);
        
        $this->db->execute(
            "UPDATE usuarios SET password_hash = ?, fecha_cambio_password = NOW() WHERE id_usuario = ?",
            [$newHash, $userId]
        );
        
        logActivity("Cambio de contraseña", 'info');
        
        return [
            'success' => true,
            'message' => 'Contraseña actualizada correctamente'
        ];
    }
}