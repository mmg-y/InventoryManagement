<?php
    //headers
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');
    header('Access-Control-Allow-Methods: DELETE');
    header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');
    
    //include files
    include_once('../includes/config.php');
    include_once('../core/initialize.php');
    
    //instantiate post
    $post = new Post($conn);
    
    //get raw posted data
    $data = json_decode(file_get_contents("php://input"));
    
    //set post id to be deleted
    $post->id = $data->id;
    
    //delete post
    if($post->delete()){
        echo json_encode(
            array('message' => 'Post Deleted')
        );
    } else {
        echo json_encode(
            array('message' => 'Post Not Deleted')
        );
    }
