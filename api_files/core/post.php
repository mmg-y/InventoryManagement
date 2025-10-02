<?php

class Post{
    //db stuff
    private $conn;
    private $table = 'posts';

    //post properties
    public $id;
    public $category_id;
    public $category_name;
    public $title;
    public $body;
    public $author;
    public $created_at;

    //constructor with db
    public function __construct($db){
        $this->conn = $db;
    }

    //get posts
    public function read(){
        //create query
        $query = 'SELECT 
            c.name as category_name,
            p.id,
            p.category_id,
            p.title,
            p.body,
            p.author,
            p.created_at
        FROM 
            ' . $this->table . ' p
        LEFT JOIN 
            categories c ON p.category_id = c.id
        ORDER BY 
            p.created_at DESC';

        //prepare statement
        $stmt = $this->conn->prepare($query);

        //execute query
        $stmt->execute();

        return $stmt;
    }

    public function create(){
        //create query
        $query = 'INSERT INTO ' . $this->table . ' SET title = ?, body = ?, author = ?, category_id = ?';

        //prepare statement
        $stmt = $this->conn->prepare($query);

        //clean data
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->body = htmlspecialchars(strip_tags($this->body));
        $this->author = htmlspecialchars(strip_tags($this->author));
        $this->category_id = htmlspecialchars(strip_tags($this->category_id));

        //bind data
        $stmt->bind_param('sssi', $this->title, $this->body, $this->author, $this->category_id);

        //execute query
        if($stmt->execute()){
            return true;
        }

        //print error if something goes wrong
        printf("Error: %s.\n", $stmt->error);

        return false;
    }

    public function update(){
        //create query
        $query = 'UPDATE ' . $this->table . ' SET title = ?, body = ?, author = ?, category_id = ? WHERE id = ?';

        //prepare statement
        $stmt = $this->conn->prepare($query);

        //clean data
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->body = htmlspecialchars(strip_tags($this->body));
        $this->author = htmlspecialchars(strip_tags($this->author));
        $this->category_id = htmlspecialchars(strip_tags($this->category_id));
        $this->id = htmlspecialchars(strip_tags($this->id));

        //bind data
        $stmt->bind_param('sssii', $this->title, $this->body, $this->author, $this->category_id, $this->id);

        //execute query
        if($stmt->execute()){
            return true;
        }

        //print error if something goes wrong
        printf("Error: %s.\n", $stmt->error);

        return false;
    }

    public function delete(){
        //create query
        $query = 'DELETE FROM ' . $this->table . ' WHERE id = ?';

        //prepare statement
        $stmt = $this->conn->prepare($query);

        //clean data
        $this->id = htmlspecialchars(strip_tags($this->id));

        //bind data
        $stmt->bind_param('i', $this->id);

        //execute query
        if($stmt->execute()){
            return true;
        }

        //print error if something goes wrong
        printf("Error: %s.\n", $stmt->error);

        return false;
    }
}