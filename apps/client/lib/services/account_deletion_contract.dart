bool confirmsAccountClosure(int statusCode, Map<String, dynamic> payload) =>
    statusCode == 200 &&
    payload['success'] == true &&
    payload['data'] is Map &&
    (payload['data'] as Map)['deletion_status'] == 'completed';

bool confirmsOptionalDataDeletion(
        int statusCode, Map<String, dynamic> payload) =>
    statusCode == 200 &&
    payload['success'] == true &&
    payload['data'] is Map &&
    (payload['data'] as Map)['deletion_status'] == 'data_deleted';
