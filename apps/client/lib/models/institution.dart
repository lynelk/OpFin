class Institution {
  final int id;
  final String name;
  final String address;
  final String phone;
  final String email;
  final String status;

  Institution({
    required this.id,
    required this.name,
    required this.address,
    required this.phone,
    required this.email,
    required this.status,
  });

  factory Institution.fromJson(Map<String, dynamic> json) {
    return Institution(
      id: json['id'],
      name: json['name'],
      address: json['address'],
      phone: json['phone'],
      email: json['email'],
      status: json['status'],
    );
  }
}
