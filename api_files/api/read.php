<?php   
    //headers
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

    //include files
    include_once('../includes/config.php');
    include_once('../core/initialize.php');

    //instantiate post
    $post = new Post($conn);

    //post query
    $result = $post->read();

    //get row count
    $num = $result->num_rows;

    //check if any posts
    if($num > 0){
        //post array
        $posts_arr = array();
        $posts_arr['data'] = array();

        while($row = $result->fetch_assoc()){
            extract($row);
            $post_item = array(
                'id' => $id,
                'category_id' => $category_id,
                'category_name' => $category_name,
                'title' => $title,
                'body' => html_entity_decode($body),
                'author' => $author,
                'created_at' => $created_at
            );
            //push to "data"
            array_push($posts_arr['data'], $post_item);
        }
        //turn to json & output
        echo json_encode($posts_arr);
    } else {
        //no posts
        echo json_encode(
            array('message' => 'No Posts Found')
        );
    }