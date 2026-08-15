<?php

class Message
{
    public function __construct(
        public string $prenom,
        public string $nom,
        public string $email,
        public ?string $objet,
        public string $message,
        public string $ip
    ) {}
}

class MessageManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(Message $message): bool
    {
        if (!$this->isValid($message)) {
            return false;
        }

        $sql = "INSERT INTO contact_messages
                (prenom, nom, email, objet, message, ip, created_at)
                VALUES (:prenom, :nom, :email, :objet, :message, :ip, NOW())";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':prenom'  => $message->prenom,
            ':nom'     => $message->nom,
            ':email'   => $message->email,
            ':objet'   => $message->objet,
            ':message' => $message->message,
            ':ip'      => $message->ip
        ]);
    }

    private function isValid(Message $message): bool
    {
        return !empty($message->nom)
            && filter_var($message->email, FILTER_VALIDATE_EMAIL);
    }
}
