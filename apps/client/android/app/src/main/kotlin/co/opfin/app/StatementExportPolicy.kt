package co.opfin.app

object StatementExportPolicy {
    const val MAX_BYTES = 4 * 1024 * 1024
    fun validate(filename: String, format: String, mode: String, size: Int): String {
        require(format in setOf("csv", "html")) { "Unsupported statement format." }
        require(mode in setOf("save", "share", "print")) { "Unsupported export operation." }
        require(mode != "print" || format == "html") { "Printing requires the HTML statement." }
        require(Regex("opfin-club-[1-9][0-9]{0,15}\\.$format").matches(filename)) { "Invalid statement filename." }
        require(size in 1..MAX_BYTES) { "The statement is empty or too large." }
        return if (format == "csv") "text/csv" else "text/html"
    }
}
