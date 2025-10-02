<?

    //headers
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');
    header('Access-Control-Allow-Methods: PUT');
    header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

    //include files
    include_once('../includes/config.php');
    include_once('../core/initialize.php');

    //instantiate post
    $post = new Post($conn);

    //get raw posted data
    $data = json_decode(file_get_contents("php://input"));
    
    //set post property values
    $post->id = $data->id;
    $post->category_id = $data->category_id;
    $post->title = $data->title;
    $post->body = $data->body;
    $post->author = $data->author;
    
    //update post
    if($post->update()){
        echo json_encode(
            array('message' => 'Post Updated')
        );
    } else {
        echo json_encode(
            array('message' => 'Post Not Updated')
        );
    }
?>