import co.opfin.app.StatementExportPolicy

fun main() {
    var checks = 0
    fun valid(name: String, format: String, mode: String, size: Int) {
        check(StatementExportPolicy.validate(name, format, mode, size).startsWith("text/"))
        checks++
    }
    fun rejected(name: String, format: String, mode: String, size: Int) {
        var rejected = false
        try { StatementExportPolicy.validate(name, format, mode, size) }
        catch (_: IllegalArgumentException) { rejected = true }
        check(rejected) { "Unsafe export accepted: $name $format $mode $size" }
        checks++
    }
    for (mode in listOf("save", "share", "print")) valid("opfin-club-123.html", "html", mode, 128)
    for (mode in listOf("save", "share")) valid("opfin-club-123.csv", "csv", mode, StatementExportPolicy.MAX_BYTES)
    for (name in listOf("../opfin-club-1.csv", "/opfin-club-1.csv", "opfin-club-1.csv.exe", "opfin-club-0.csv", "opfin-club-1.csv\n", "other.csv")) rejected(name, "csv", "save", 10)
    rejected("opfin-club-1.csv", "csv", "print", 10)
    rejected("opfin-club-1.html", "html", "upload", 10)
    rejected("opfin-club-1.html", "html", "save", 0)
    rejected("opfin-club-1.html", "html", "save", StatementExportPolicy.MAX_BYTES + 1)
    rejected("opfin-club-1.html", "pdf", "save", 10)
    println("PASS: $checks native export policy assertions")
}
