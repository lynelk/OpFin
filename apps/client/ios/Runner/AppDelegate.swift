import CoreLocation
import Flutter
import UIKit

@main
@objc class AppDelegate: FlutterAppDelegate, CLLocationManagerDelegate {
  private let locationChannelName = "co.opfin/location"
  private var locationManager: CLLocationManager?
  private var pendingLocationResult: FlutterResult?
  private var requestedPrecise = false
  private var statementExports: ClubStatementExports?

  override func application(_ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?) -> Bool {
    GeneratedPluginRegistrant.register(with: self)
    if let controller = window?.rootViewController as? FlutterViewController {
      let exports = ClubStatementExports(controller: controller)
      statementExports = exports
      FlutterMethodChannel(name: "co.opfin/club_statement_export", binaryMessenger: controller.binaryMessenger)
        .setMethodCallHandler { [weak exports] call, result in
          guard let exports else {
            result(FlutterError(code: "export_unavailable", message: "The export screen is unavailable.", details: nil))
            return
          }
          if call.method == "export" { exports.handle(call.arguments, result: result) }
          else { result(FlutterMethodNotImplemented) }
        }
      let channel = FlutterMethodChannel(name: locationChannelName, binaryMessenger: controller.binaryMessenger)
      channel.setMethodCallHandler { [weak self] call, result in
        guard let self else { return }
        switch call.method {
        case "currentLocation":
          guard self.pendingLocationResult == nil else {
            result(FlutterError(code: "location_busy", message: "A location request is already in progress.", details: nil))
            return
          }
          let arguments = call.arguments as? [String: Any]
          self.requestedPrecise = (arguments?["precision"] as? String) == "precise"
          self.pendingLocationResult = result
          self.requestCurrentLocation()
        case "openMaps":
          let arguments = call.arguments as? [String: Any]
          guard let value = arguments?["url"] as? String, let url = URL(string: value) else {
            result(FlutterError(code: "invalid_url", message: "A map URL is required.", details: nil))
            return
          }
          UIApplication.shared.open(url, options: [:]) { opened in
            if opened { result(true) }
            else { result(FlutterError(code: "maps_unavailable", message: "No map application can open this location.", details: nil)) }
          }
        default: result(FlutterMethodNotImplemented)
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
    manager.desiredAccuracy = requestedPrecise ? kCLLocationAccuracyBest : kCLLocationAccuracyKilometer
    switch manager.authorizationStatus {
    case .notDetermined: manager.requestWhenInUseAuthorization()
    case .authorizedWhenInUse, .authorizedAlways: manager.requestLocation()
    case .denied, .restricted: finishLocation(errorCode: "location_permission_denied", message: "Location permission was not granted.")
    @unknown default: finishLocation(errorCode: "location_unavailable", message: "Location is unavailable.")
    }
  }

  func locationManagerDidChangeAuthorization(_ manager: CLLocationManager) {
    guard pendingLocationResult != nil else { return }
    switch manager.authorizationStatus {
    case .authorizedWhenInUse, .authorizedAlways: manager.requestLocation()
    case .denied, .restricted: finishLocation(errorCode: "location_permission_denied", message: "Location permission was not granted.")
    case .notDetermined: break
    @unknown default: finishLocation(errorCode: "location_unavailable", message: "Location is unavailable.")
    }
  }

  func locationManager(_ manager: CLLocationManager, didUpdateLocations locations: [CLLocation]) {
    guard let location = locations.last else {
      finishLocation(errorCode: "location_unavailable", message: "Current location is unavailable.")
      return
    }
    let result = pendingLocationResult
    pendingLocationResult = nil
    let actualPrecision: String
    if #available(iOS 14.0, *) { actualPrecision = manager.accuracyAuthorization == .fullAccuracy ? "precise" : "approximate" }
    else { actualPrecision = "precise" }
    result?(["latitude": location.coordinate.latitude, "longitude": location.coordinate.longitude,
      "accuracy_metres": max(0, Int(location.horizontalAccuracy)),
      "captured_at": Int(location.timestamp.timeIntervalSince1970 * 1000),
      "requested_precision": requestedPrecise ? "precise" : "approximate", "actual_precision": actualPrecision])
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

// Kept in the existing Runner compilation unit so no unregistered Xcode source file is required.
private final class ClubStatementExports: NSObject, UIDocumentPickerDelegate, UIAdaptivePresentationControllerDelegate {
  private weak var controller: UIViewController?
  private var pending: FlutterResult?
  private var temporaryURL: URL?

  init(controller: UIViewController) { self.controller = controller; super.init() }

  func handle(_ arguments: Any?, result: @escaping FlutterResult) {
    guard pending == nil else {
      result(FlutterError(code: "export_busy", message: "Finish the current export first.", details: nil)); return
    }
    guard let input = arguments as? [String: Any], let filename = input["filename"] as? String,
      let format = input["format"] as? String, let mode = input["mode"] as? String,
      let typed = input["bytes"] as? FlutterStandardTypedData,
      ["csv", "html"].contains(format), ["save", "share", "print"].contains(mode),
      mode != "print" || format == "html",
      filename.range(of: "^opfin-club-[1-9][0-9]{0,15}\\.\(format)$", options: .regularExpression) != nil,
      typed.data.count > 0, typed.data.count <= 4 * 1024 * 1024,
      let root = controller else {
      result(FlutterError(code: "invalid_export", message: "The export document is invalid.", details: nil)); return
    }
    var presenter = root
    while let presented = presenter.presentedViewController { presenter = presented }
    pending = result
    if mode == "print" {
      guard let html = String(data: typed.data, encoding: .utf8), UIPrintInteractionController.isPrintingAvailable else {
        finish(error: "The device print service is unavailable."); return
      }
      let printer = UIPrintInteractionController.shared
      let info = UIPrintInfo(dictionary: nil)
      info.jobName = filename
      info.outputType = .general
      printer.printInfo = info
      printer.printFormatter = UIMarkupTextPrintFormatter(markupText: html)
      let completion = { [weak self] (_: UIPrintInteractionController, completed: Bool, error: Error?) in
        if error != nil { self?.finish(error: "The print operation failed.") }
        else { self?.finish(status: completed ? "completed" : "cancelled") }
      }
      let shown: Bool
      if UIDevice.current.userInterfaceIdiom == .pad {
        shown = printer.present(from: CGRect(x: presenter.view.bounds.midX, y: presenter.view.bounds.midY, width: 1, height: 1),
          in: presenter.view, animated: true, completionHandler: completion)
      } else { shown = printer.present(animated: true, completionHandler: completion) }
      if !shown { finish(error: "The device could not open the print dialogue.") }
      return
    }
    do {
      let manager = FileManager.default
      let directory = manager.temporaryDirectory.appendingPathComponent("opfin-club-exports", isDirectory: true)
      try manager.createDirectory(at: directory, withIntermediateDirectories: true)
      let previous = try manager.contentsOfDirectory(at: directory, includingPropertiesForKeys: [.contentModificationDateKey])
      for file in previous {
        if let modified = try file.resourceValues(forKeys: [.contentModificationDateKey]).contentModificationDate,
          modified < Date().addingTimeInterval(-86400) { try? manager.removeItem(at: file) }
      }
      let url = directory.appendingPathComponent(UUID().uuidString + "-" + filename)
      try typed.data.write(to: url, options: .atomic)
      try manager.setAttributes([.protectionKey: FileProtectionType.complete], ofItemAtPath: url.path)
      temporaryURL = url
      if mode == "save" {
        let picker: UIDocumentPickerViewController
        if #available(iOS 14.0, *) { picker = UIDocumentPickerViewController(forExporting: [url], asCopy: true) }
        else { picker = UIDocumentPickerViewController(url: url, in: .exportToService) }
        picker.delegate = self
        picker.modalPresentationStyle = .formSheet
        picker.presentationController?.delegate = self
        presenter.present(picker, animated: true)
      } else {
        let share = UIActivityViewController(activityItems: [url], applicationActivities: nil)
        share.popoverPresentationController?.sourceView = presenter.view
        share.popoverPresentationController?.sourceRect = CGRect(x: presenter.view.bounds.midX, y: presenter.view.bounds.midY, width: 1, height: 1)
        share.completionWithItemsHandler = { [weak self] _, completed, _, error in
          if error != nil { self?.finish(error: "Sharing failed. Check the selected destination.") }
          else { self?.finish(status: completed ? "completed" : "cancelled") }
        }
        presenter.present(share, animated: true)
      }
    } catch { finish(error: "The statement could not be prepared for export.") }
  }

  func documentPicker(_ controller: UIDocumentPickerViewController, didPickDocumentsAt urls: [URL]) { finish(status: "saved") }
  func documentPickerWasCancelled(_ controller: UIDocumentPickerViewController) { finish(status: "cancelled") }
  func presentationControllerDidDismiss(_ presentationController: UIPresentationController) { finish(status: "cancelled") }

  private func finish(status: String? = nil, error: String? = nil) {
    let result = pending
    pending = nil
    if let file = temporaryURL { try? FileManager.default.removeItem(at: file) }
    temporaryURL = nil
    if let error { result?(FlutterError(code: "export_failed", message: error, details: nil)) }
    else { result?(["status": status ?? "cancelled"]) }
  }
}
