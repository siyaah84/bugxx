<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';
session_start();
header('Content-Type: application/json');

try {
    $client = new MongoDB\Client("mongodb://localhost:27017");
    $db = $client->pollsystem;
    $polls = $db->polls;
    $options = $db->options;
    $votes = $db->votes;

    $inputData = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? $_POST['action'] ?? ($inputData['action'] ?? '');
    $response = ['success' => false, 'message' => 'Invalid action'];

    function get_user_id($inputData) {
        if (isset($_SESSION['user_id'])) return $_SESSION['user_id'];
        if (isset($inputData['user_id'])) return $inputData['user_id'];
        return null;
    }

    if ($action === 'create') {
        $data = $inputData;
        $question = $data['question'] ?? '';
        $expiry = $data['expiry'] ?? '';
        $user_id = get_user_id($data);
        $opts = $data['options'] ?? [];

        if ($question && $expiry && $user_id && count($opts) >= 2) {
            $pollInsert = $polls->insertOne([
                'question' => $question,
                'expiry' => $expiry,
                'user_id' => $user_id,
                'created_at' => date('c')
            ]);
            $poll_id = (string)$pollInsert->getInsertedId();
            foreach ($opts as $opt) {
                $options->insertOne([
                    'poll_id' => $poll_id,
                    'text' => $opt
                ]);
            }
            $response = ['success' => true, 'message' => 'Poll created'];
        } else {
            $response = ['success' => false, 'message' => 'Invalid poll data'];
        }
    } elseif ($action === 'vote') {
        $data = $inputData;
        $poll_id = $data['poll_id'] ?? '';
        $option_id = $data['option_id'] ?? '';
        $user_id = get_user_id($data);

        if ($poll_id && $option_id && $user_id) {
            $already = $votes->findOne(['poll_id' => $poll_id, 'user_id' => $user_id]);
            if ($already) {
                $response = ['success' => false, 'message' => 'You have already voted'];
            } else {
                $votes->insertOne([
                    'poll_id' => $poll_id,
                    'option_id' => $option_id,
                    'user_id' => $user_id
                ]);
                $response = ['success' => true, 'message' => 'Vote recorded'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Incomplete vote data'];
        }
    } elseif ($action === 'list') {
        $now = date('c');
        $cursor = $polls->find(['expiry' => ['$gt' => $now]]);
        $result = [];
        foreach ($cursor as $poll) {
            $poll_id = (string)$poll->_id;
            $optsCursor = $options->find(['poll_id' => $poll_id]);
            $opts = [];
            foreach ($optsCursor as $opt) {
                $opt_id = (string)$opt->_id;
                $voteCount = $votes->countDocuments(['option_id' => $opt_id]);
                $opts[] = [
                    'id' => $opt_id,
                    'text' => $opt['text'],
                    'votes' => $voteCount
                ];
            }
            $votersCursor = $votes->find(['poll_id' => $poll_id], ['projection' => ['user_id' => 1]]);
            $voters = [];
            foreach ($votersCursor as $v) $voters[] = $v['user_id'];
            $result[] = [
                'id' => $poll_id,
                'question' => $poll['question'],
                'expiry' => $poll['expiry'],
                'options' => $opts,
                'voters' => $voters
            ];
        }
        $response = $result;
    }

    echo json_encode($response);

} catch (Throwable $e) {
    // Always return JSON, even on error
    echo json_encode(['success' => false, 'message' => 'Server error']);
}