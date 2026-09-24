import CoreLocation
import Flutter
import UIKit

@main
@objc class AppDelegate: FlutterAppDelegate, CLLocationManagerDelegate {
  private let locationChannelName = "co.opfin/location"
  private var locationManager: CLLocationManager?
  private var pendingLocationResult: FlutterResult?
  private var requestedPrecise = false

  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    GeneratedPluginRegistrant.register(with: self)

    if let controller = window?.rootViewController as? FlutterViewController {
      let channel = FlutterMethodChannel(
        name: locationChannelName,
        binaryMessenger: controller.binaryMessenger
      )

      channel.setMethodCallHandler { [weak self] call, result in
        guard let self else { return }
        switch call.method {
        case "currentLocation":
          guard self.pendingLocationResult == nil else {
            result(FlutterError(
              code: "location_busy",
              message: "A location request is already in progress.",
              details: nil
            ))
            return
          }
          let arguments = call.arguments as? [String: Any]
          self.requestedPrecise = (arguments?["precision"] as? String) == "precise"
          self.pendingLocationResult = result
          self.requestCurrentLocation()
        case "openMaps":
          let arguments = call.arguments as? [String: Any]
          guard
            let value = arguments?["url"] as? String,
            let url = URL(string: value)
          else {
            result(FlutterError(code: "invalid_url", message: "A map URL is required.", details: nil))
            return
          }

          UIApplication.shared.open(url, options: [:]) { opened in
            if opened {
              result(true)
            } else {
              result(FlutterError(
                code: "maps_unavailable",
                message: "No map application can open this location.",
                details: nil
              ))
            }
          }
        default:
          result(FlutterMethodNotImplemented)
        }
      }
    }

    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }

  private func requestCurrentLocation() {
    guard CLLocationManager.locationServicesEnabled() else {
      finishLocation(errorCode: "location_disabled", message: "Device location services are turned off.")
      return
    }

    let manager = locationManager ?? CLLocationManager()
    locationManager = manager
    manager.delegate = self
    manager.desiredAccuracy = requestedPrecise
      ? kCLLocationAccuracyBest
      : kCLLocationAccuracyKilometer

    switch manager.authorizationStatus {
    case .notDetermined:
      manager.requestWhenInUseAuthorization()
    case .authorizedWhenInUse, .authorizedAlways:
      manager.requestLocation()
    case .denied, .restricted:
      finishLocation(
        errorCode: "location_permission_denied",
        message: "Location permission was not granted."
      )
    @unknown default:
      finishLocation(errorCode: "location_unavailable", message: "Location is unavailable.")
    }
  }

  func locationManagerDidChangeAuthorization(_ manager: CLLocationManager) {
    guard pendingLocationResult != nil else { return }

    switch manager.authorizationStatus {
    case .authorizedWhenInUse, .authorizedAlways:
      manager.requestLocation()
    case .denied, .restricted:
      finishLocation(
        errorCode: "location_permission_denied",
        message: "Location permission was not granted."
      )
    case .notDetermined:
      break
    @unknown default:
      finishLocation(errorCode: "location_unavailable", message: "Location is unavailable.")
    }
  }

  func locationManager(_ manager: CLLocationManager, didUpdateLocations locations: [CLLocation]) {
    guard let location = locations.last else {
      finishLocation(errorCode: "location_unavailable", message: "Current location is unavailable.")
      return
    }

    let result = pendingLocationResult
    pendingLocationResult = nil

    result?([
      "latitude": location.coordinate.latitude,
      "longitude": location.coordinate.longitude,
      "accuracy_metres": max(0, Int(location.horizontalAccuracy)),
      "captured_at": Int(location.timestamp.timeIntervalSince1970 * 1000),
      "requested_precision": requestedPrecise ? "precise" : "approximate",
      "actual_precision": manager.accuracyAuthorization == .fullAccuracy ? "precise" : "approximate"
    ])
  }

  func locationManager(_ manager: CLLocationManager, didFailWithError error: Error) {
    finishLocation(errorCode: "location_unavailable", message: "Current location is unavailable.")
  }

  private func finishLocation(errorCode: String, message: String) {
    let result = pendingLocationResult
    pendingLocationResult = nil
    result?(FlutterError(code: errorCode, message: message, details: nil))
  }
}
