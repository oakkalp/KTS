import 'dart:convert';
import 'dart:io';

void main() async {
  // Test different API URLs
  final urls = [
    'http://localhost/kuryefullsistem/api/mobile/test.php',
    'http://10.0.2.2/kuryefullsistem/api/mobile/test.php',
    'http://192.168.1.137/kuryefullsistem/api/mobile/test.php',
  ];
  
  final client = HttpClient();
  
  for (final url in urls) {
    print('\n=== Testing: $url ===');
    
    try {
      final uri = Uri.parse(url);
      final request = await client.getUrl(uri);
      request.headers.set('Content-Type', 'application/json');
      
      final response = await request.close();
      final responseBody = await response.transform(utf8.decoder).join();
      
      print('Status: ${response.statusCode}');
      print('Headers: ${response.headers}');
      print('Body: ${responseBody.substring(0, responseBody.length > 200 ? 200 : responseBody.length)}...');
      
      if (response.statusCode == 200) {
        try {
          final json = jsonDecode(responseBody);
          print('JSON Parse: SUCCESS');
          print('Success: ${json['success']}');
        } catch (e) {
          print('JSON Parse: FAILED - $e');
        }
      }
      
    } catch (e) {
      print('ERROR: $e');
    }
  }
  
  client.close();
  
  // Test login
  print('\n=== Testing Login ===');
  await testLogin();
}

Future<void> testLogin() async {
  final client = HttpClient();
  
  try {
    final uri = Uri.parse('http://10.0.2.2/kuryefullsistem/api/mobile/auth/login_simple.php');
    final request = await client.postUrl(uri);
    request.headers.set('Content-Type', 'application/json');
    
    final body = jsonEncode({
      'username': 'testkurye',
      'password': '123456',
    });
    
    request.add(utf8.encode(body));
    
    final response = await request.close();
    final responseBody = await response.transform(utf8.decoder).join();
    
    print('Login Status: ${response.statusCode}');
    print('Login Response: $responseBody');
    
    if (response.statusCode == 200) {
      try {
        final json = jsonDecode(responseBody);
        print('Login Success: ${json['success']}');
        if (json['success']) {
          print('Token: ${json['data']['token'].toString().substring(0, 50)}...');
        }
      } catch (e) {
        print('Login JSON Parse Error: $e');
      }
    }
    
  } catch (e) {
    print('Login Error: $e');
  }
  
  client.close();
}
