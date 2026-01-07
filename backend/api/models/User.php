<?php
// This is the User model. Think of it like a translator between our code 
// and the "users" table in the database.
require_once __DIR__ . '/Model.php';

class User extends Model {
    protected $table = 'users'; // Telling the code which table to look at.
    
    // These are the fields we are allowed to fill in. 
    // It's a safety measure to prevent hackers from adding weird stuff.
    protected $fillable = [
        'user_id', 'first_name', 'last_name', 'gender', 'username', 
        'password', 'phone', 'email', 'profile_pic', 'user_type', 'status', 'first_login'
    ];

    // This handles saving a brand new user.
    public function createUser($data) {
        // We NEVER store raw passwords. We hash them so even if someone sees the database, 
        // they can't see the real password. BCRYPT is really strong!
        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        
        // Teachers get a "first_login" flag set to 1. 
        // This helps the system know if they need to change their temporary password.
        if (isset($data['user_type']) && $data['user_type'] === 'Teacher') {
            if (!isset($data['first_login'])) {
                $data['first_login'] = 1;
            }
        }
        
        return $this->create($data);
    }

    // A quick way to find a user by their username.
    public function findByUsername($username) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }

    // Finding someone by their email address.
    public function findByEmail($email) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    
    // Finding a student or teacher by their school ID (like STU-101).
    public function findByUserId($userId) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    // Getting the whole list of users. If we tell it a type, it filters the list.
    public function getAllUsers($type = null) {
        $sql = "SELECT id, user_id, first_name, last_name, username, password, email, phone, user_type, status, profile_pic, created_at FROM {$this->table}";
        $params = [];
        
        if ($type) {
            $sql .= " WHERE user_type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        return $this->query($sql, $params)->fetchAll();
    }

    // Changing an existing user's info. We hash the password again if it's being changed.
    public function updateUser($id, $data) {
        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        return $this->update($id, $data);
    }

    // I built this to auto-make usernames like 'tea_1001', 'tea_1002', etc.
    // It's smarter than a random string because it's sequential.
    public function generateSequentialUsername($type = 'Teacher') {
        $prefix = ($type === 'Teacher') ? 'tea_' : 'usr_';
        
        // Sometimes collisions happen (if kids try to mess with the system), 
        // so I added a loop to keep trying until we find a free one.
        $attempt = 0;
        do {
            $sql = "SELECT username FROM {$this->table} 
                    WHERE username LIKE '$prefix%' 
                    ORDER BY CAST(SUBSTRING(username, " . (strlen($prefix) + 1) . ") AS UNSIGNED) DESC 
                    LIMIT 1";
            
            $lastUsername = $this->query($sql)->fetchColumn();
            
            if (!$lastUsername) {
                $newUsername = $prefix . "1001"; // Starting point.
            } else {
                $lastNumber = (int)substr($lastUsername, strlen($prefix));
                $newUsername = $prefix . ($lastNumber + 1);
            }
            
            // Just double checking it doesn't exist.
            if (!$this->findByUsername($newUsername)) {
                return $newUsername;
            }
            $prefix .= "next_"; 
            $attempt++;
        } while ($attempt < 5);
        
        return $prefix . mt_rand(10000, 99999);
    }

    // This makes professional-looking IDs like 'ADM-1' or 'STU-42'.
    public function generateNextUserId($type) {
        $prefix = match($type) {
            'Admin' => 'ADM-',
            'Teacher' => 'TCH-',
            'Student' => 'STU-',
            default => 'USR-'
        };

        $sql = "SELECT user_id FROM {$this->table} 
                WHERE user_id LIKE '$prefix%' 
                ORDER BY CAST(SUBSTRING(user_id, " . (strlen($prefix) + 1) . ") AS UNSIGNED) DESC 
                LIMIT 1";
        
        $lastId = $this->query($sql)->fetchColumn();
        
        if (!$lastId) {
            return $prefix . "1";
        }
        
        $lastNumber = (int)substr($lastId, strlen($prefix));
        $newNumber = $lastNumber + 1;
        
        return $prefix . $newNumber;
    }

    // Making a random temporary password. It's basically like a lottery for characters!
    public function generateTempPassword($length = 8) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#$!';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $password;
    }

    // Just counting how many rows are in the table. Simple math!
    public function countAll() {
        return (int)$this->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
    }
}
