import 'package:flutter/material.dart';
import 'package:opfin/services/location_context_api.dart';
import 'package:opfin/services/platform_location_service.dart';

class LocationContextScreen extends StatefulWidget {
  const LocationContextScreen({
    super.key,
    required this.subjectType,
    required this.subjectId,
    required this.purpose,
    required this.title,
    this.description,
    this.countryCode = 'UG',
    this.preciseRecommended = false,
    this.readOnly = false,
  });

  final String subjectType;
  final int subjectId;
  final String purpose;
  final String title;
  final String? description;
  final String countryCode;
  final bool preciseRecommended;
  final bool readOnly;

  @override
  State<LocationContextScreen> createState() => _LocationContextScreenState();
}

class _LocationContextScreenState extends State<LocationContextScreen> {
  late Future<Map<String, dynamic>> _state;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _state = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final results = await Future.wait([
      LocationContextApi.status(),
      LocationContextApi.list(
        subjectType: widget.subjectType,
        subjectId: widget.subjectId,
      ),
    ]);
    final locations = (results[1] as List<Map<String, dynamic>>)
        .where((item) => item['purpose'] == widget.purpose)
        .toList();

    return {
      'status': results[0],
      'location': locations.isEmpty ? null : locations.first,
    };
  }

  Future<void> _refresh() async {
    setState(() => _state = _load());
    await _state;
  }

  Future<void> _useDevice(Map<String, dynamic> status) async {
    if (_saving) return;
    setState(() => _saving = true);
    try {
      final precision = widget.preciseRecommended &&
              PlatformLocationService.supportsPreciseDeviceLocation
          ? 'precise'
          : 'approximate';
      final device = await PlatformLocationService.currentLocation(
        precision: precision,
      );
      final actualPrecision =
          device['actual_precision']?.toString() ?? precision;
      final storagePrecision =
          precision == 'precise' && actualPrecision == 'approximate'
              ? 'approximate'
              : precision;
      await LocationContextApi.save(
        subjectType: widget.subjectType,
        subjectId: widget.subjectId,
        purpose: widget.purpose,
        source: 'device',
        precisionLevel: storagePrecision,
        consentPurpose: widget.purpose,
        latitude: (device['latitude'] as num).toDouble(),
        longitude: (device['longitude'] as num).toDouble(),
        accuracyMetres: (device['accuracy_metres'] as num?)?.toInt(),
        resolveWithGoogle: status['google_maps_configured'] == true,
      );
      if (!mounted) return;
      await _refresh();
    } catch (error) {
      _message(error.toString());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _searchPlace(bool googleConfigured) async {
    if (!googleConfigured) {
      _message('Place search is unavailable until Google Maps is configured.');
      return;
    }

    final selected = await Navigator.push<Map<String, dynamic>>(
      context,
      MaterialPageRoute(
        builder: (_) => PlaceSearchScreen(countryCode: widget.countryCode),
      ),
    );
    if (selected == null) return;

    setState(() => _saving = true);
    try {
      await LocationContextApi.save(
        subjectType: widget.subjectType,
        subjectId: widget.subjectId,
        purpose: widget.purpose,
        source: 'google_place',
        precisionLevel: widget.preciseRecommended ? 'precise' : 'locality',
        consentPurpose: widget.purpose,
        placeId: selected['place_id']?.toString(),
      );
      if (!mounted) return;
      await _refresh();
    } catch (error) {
      _message(error.toString());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _manual() async {
    final name = TextEditingController();
    final address = TextEditingController();
    final region = TextEditingController();

    final ok = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Add location manually'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: name,
                decoration: const InputDecoration(
                  labelText: 'Place or area name',
                ),
              ),
              TextField(
                controller: address,
                decoration: const InputDecoration(
                  labelText: 'Address or description',
                ),
              ),
              TextField(
                controller: region,
                decoration: const InputDecoration(
                  labelText: 'Region / district',
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Save'),
          ),
        ],
      ),
    );

    if (ok != true || (name.text.trim().isEmpty && address.text.trim().isEmpty)) {
      return;
    }

    setState(() => _saving = true);
    try {
      await LocationContextApi.save(
        subjectType: widget.subjectType,
        subjectId: widget.subjectId,
        purpose: widget.purpose,
        source: 'manual',
        precisionLevel: 'locality',
        consentPurpose: widget.purpose,
        placeName: name.text.trim().isEmpty ? null : name.text.trim(),
        formattedAddress:
            address.text.trim().isEmpty ? null : address.text.trim(),
        countryCode: widget.countryCode,
        adminArea1: region.text.trim().isEmpty ? null : region.text.trim(),
      );
      if (!mounted) return;
      await _refresh();
    } catch (error) {
      _message(error.toString());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _remove(Map<String, dynamic> location) async {
    final id = (location['id'] as num?)?.toInt();
    if (id == null) return;
    setState(() => _saving = true);
    try {
      await LocationContextApi.delete(id);
      if (!mounted) return;
      await _refresh();
    } catch (error) {
      _message(error.toString());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _message(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message.replaceFirst('Exception: ', ''))),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(widget.title)),
        body: FutureBuilder<Map<String, dynamic>>(
          future: _state,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(snapshot.error.toString(), textAlign: TextAlign.center),
                      const SizedBox(height: 12),
                      FilledButton(
                        onPressed: _refresh,
                        child: const Text('Try again'),
                      ),
                    ],
                  ),
                ),
              );
            }

            final data = snapshot.data ?? const <String, dynamic>{};
            final status =
                (data['status'] as Map?)?.cast<String, dynamic>() ??
                    const <String, dynamic>{};
            final location =
                (data['location'] as Map?)?.cast<String, dynamic>();
            final googleConfigured =
                status['google_maps_configured'] == true;

            return RefreshIndicator(
              onRefresh: _refresh,
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  if (widget.description != null) ...[
                    Text(widget.description!),
                    const SizedBox(height: 16),
                  ],
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Text(
                        PlatformLocationService.supportsPreciseDeviceLocation
                            ? 'OpFin requests location only for the task you choose. Background tracking is not used. Approximate location is preferred unless the task needs a precise asset or risk location.'
                            : 'Device location is approximate and optional. For an exact asset or risk location, search for a place or enter its address manually. Background tracking is not used.',
                      ),
                    ),
                  ),
                  if (location != null) ...[
                    _LocationCard(
                      location: location,
                      staticMapsEnabled:
                          status['static_maps_enabled'] == true,
                      onOpenMaps: () async {
                        final url = location['maps_url']?.toString();
                        if (url == null || url.isEmpty) return;
                        try {
                          await PlatformLocationService.openMaps(url);
                        } catch (error) {
                          _message(error.toString());
                        }
                      },
                      onRemove: widget.readOnly || _saving
                          ? null
                          : () => _remove(location),
                    ),
                    const SizedBox(height: 12),
                  ] else
                    const Card(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: Text('No location has been added for this purpose yet.'),
                      ),
                    ),
                  if (widget.readOnly)
                    const Card(
                      child: Padding(
                        padding: EdgeInsets.all(14),
                        child: Text(
                          'You can view this location. A group administrator or authorised owner manages changes.',
                        ),
                      ),
                    )
                  else ...[
                    FilledButton.icon(
                      onPressed: _saving ? null : () => _useDevice(status),
                      icon: const Icon(Icons.my_location),
                      label: Text(
                        widget.preciseRecommended &&
                                PlatformLocationService.supportsPreciseDeviceLocation
                            ? 'Use location for this asset / risk'
                            : 'Use my approximate location',
                      ),
                    ),
                    const SizedBox(height: 8),
                    OutlinedButton.icon(
                      onPressed:
                          _saving ? null : () => _searchPlace(googleConfigured),
                      icon: const Icon(Icons.search),
                      label: const Text('Search for a place'),
                    ),
                    const SizedBox(height: 8),
                    TextButton.icon(
                      onPressed: _saving ? null : _manual,
                      icon: const Icon(Icons.edit_location_alt_outlined),
                      label: const Text('Enter location manually'),
                    ),
                  ],
                  if (!googleConfigured) ...[
                    const SizedBox(height: 12),
                    const Text(
                      'Google place search and map previews are not currently activated. Manual location remains available.',
                    ),
                  ],
                ],
              ),
            );
          },
        ),
      );
}

class _LocationCard extends StatelessWidget {
  const _LocationCard({
    required this.location,
    required this.staticMapsEnabled,
    required this.onOpenMaps,
    required this.onRemove,
  });

  final Map<String, dynamic> location;
  final bool staticMapsEnabled;
  final VoidCallback onOpenMaps;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) {
    final id = (location['id'] as num?)?.toInt();
    final hasCoordinates =
        location['latitude'] != null && location['longitude'] != null;

    return Card(
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (staticMapsEnabled && hasCoordinates && id != null)
            FutureBuilder<Map<String, String>>(
              future: LocationContextApi.imageHeaders(),
              builder: (context, snapshot) {
                if (!snapshot.hasData) {
                  return const SizedBox(
                    height: 160,
                    child: Center(child: CircularProgressIndicator()),
                  );
                }
                return Image.network(
                  LocationContextApi.staticMapUri(id).toString(),
                  headers: snapshot.data,
                  height: 160,
                  width: double.infinity,
                  fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) => const SizedBox.shrink(),
                );
              },
            ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  location['place_name']?.toString() ??
                      location['locality']?.toString() ??
                      'Saved location',
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                if (location['formatted_address'] != null)
                  Text(location['formatted_address'].toString()),
                if (location['plus_code'] != null)
                  Text('Plus Code: ' + location['plus_code'].toString()),
                const SizedBox(height: 6),
                Text(
                  (location['precision_level'] ?? 'location')
                          .toString()
                          .replaceAll('_', ' ') +
                      ' · ' +
                      (location['verification_status'] ?? 'user declared')
                          .toString()
                          .replaceAll('_', ' '),
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  children: [
                    if (location['maps_url'] != null)
                      OutlinedButton.icon(
                        onPressed: onOpenMaps,
                        icon: const Icon(Icons.directions_outlined),
                        label: const Text('Open map'),
                      ),
                    if (onRemove != null)
                      TextButton.icon(
                        onPressed: onRemove,
                        icon: const Icon(Icons.delete_outline),
                        label: const Text('Remove'),
                      ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class PlaceSearchScreen extends StatefulWidget {
  const PlaceSearchScreen({super.key, required this.countryCode});
  final String countryCode;

  @override
  State<PlaceSearchScreen> createState() => _PlaceSearchScreenState();
}

class _PlaceSearchScreenState extends State<PlaceSearchScreen> {
  final _query = TextEditingController();
  List<Map<String, dynamic>> _results = const [];
  bool _searching = false;
  String? _error;
  final String _sessionToken =
      'opfin-' + DateTime.now().microsecondsSinceEpoch.toString();

  Future<void> _search() async {
    final query = _query.text.trim();
    if (query.length < 2) return;
    setState(() {
      _searching = true;
      _error = null;
    });
    try {
      final results = await LocationContextApi.autocomplete(
        query,
        countryCode: widget.countryCode,
        sessionToken: _sessionToken,
      );
      if (mounted) setState(() => _results = results);
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _searching = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Search place')),
        body: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            TextField(
              controller: _query,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => _search(),
              decoration: InputDecoration(
                labelText: 'Place, landmark or address',
                suffixIcon: IconButton(
                  onPressed: _searching ? null : _search,
                  icon: const Icon(Icons.search),
                ),
              ),
            ),
            if (_searching)
              const Padding(
                padding: EdgeInsets.all(20),
                child: Center(child: CircularProgressIndicator()),
              ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Text(_error!),
              ),
            ..._results.map(
              (item) => Card(
                child: ListTile(
                  leading: const Icon(Icons.place_outlined),
                  title: Text(
                    item['main_text']?.toString() ??
                        item['text']?.toString() ??
                        'Place',
                  ),
                  subtitle: item['secondary_text'] == null
                      ? null
                      : Text(item['secondary_text'].toString()),
                  onTap: () => Navigator.pop(context, item),
                ),
              ),
            ),
          ],
        ),
      );
}

class NearbyServicesScreen extends StatefulWidget {
  const NearbyServicesScreen({super.key});

  @override
  State<NearbyServicesScreen> createState() => _NearbyServicesScreenState();
}

class _NearbyServicesScreenState extends State<NearbyServicesScreen> {
  Future<List<Map<String, dynamic>>>? _services;
  bool _loading = false;

  Future<void> _find() async {
    if (_loading) return;
    setState(() => _loading = true);
    try {
      final current = await PlatformLocationService.currentLocation(
        precision: 'approximate',
      );
      final rawLatitude = (current['latitude'] as num).toDouble();
      final rawLongitude = (current['longitude'] as num).toDouble();
      final approximateLatitude = (rawLatitude * 1000).round() / 1000;
      final approximateLongitude = (rawLongitude * 1000).round() / 1000;
      final future = LocationContextApi.nearbyServices(
        approximateLatitude,
        approximateLongitude,
      );
      setState(() => _services = future);
      await future;
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(error.toString().replaceFirst('Exception: ', '')),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Services near me')),
        body: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            const Text(
              'Find participating service points',
              style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            const Text(
              'Your approximate location is used for this search and is not stored by the nearby-service request.',
            ),
            const SizedBox(height: 18),
            FilledButton.icon(
              onPressed: _loading ? null : _find,
              icon: const Icon(Icons.near_me_outlined),
              label: Text(_loading ? 'Finding…' : 'Find nearby services'),
            ),
            const SizedBox(height: 16),
            if (_services != null)
              FutureBuilder<List<Map<String, dynamic>>>(
                future: _services,
                builder: (context, snapshot) {
                  if (snapshot.connectionState != ConnectionState.done) {
                    return const Center(child: CircularProgressIndicator());
                  }
                  if (snapshot.hasError) {
                    return Text(snapshot.error.toString());
                  }
                  final services =
                      snapshot.data ?? const <Map<String, dynamic>>[];
                  if (services.isEmpty) {
                    return const Card(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: Text(
                          'No participating service point is currently recorded nearby.',
                        ),
                      ),
                    );
                  }
                  return Column(
                    children: services
                        .map(
                          (service) => Card(
                            child: ListTile(
                              leading: const Icon(Icons.location_on_outlined),
                              title: Text(
                                service['partner_name']?.toString() ??
                                    service['place_name']?.toString() ??
                                    'Service point',
                              ),
                              subtitle: Text(
                                (service['formatted_address'] ??
                                            service['locality'] ??
                                            '')
                                        .toString() +
                                    ' · ' +
                                    (service['distance_km'] ?? '?').toString() +
                                    ' km',
                              ),
                              trailing: service['maps_url'] == null
                                  ? null
                                  : const Icon(Icons.directions_outlined),
                              onTap: service['maps_url'] == null
                                  ? null
                                  : () => PlatformLocationService.openMaps(
                                        service['maps_url'].toString(),
                                      ),
                            ),
                          ),
                        )
                        .toList(),
                  );
                },
              ),
          ],
        ),
      );
}
