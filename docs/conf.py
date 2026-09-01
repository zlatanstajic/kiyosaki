project = "Kiyosaki"
copyright = "2026, Zlatan Stajic"
author = "Zlatan Stajic"

extensions = ["myst_parser"]
source_suffix = {".md": "markdown"}
exclude_patterns = ["_build", "Thumbs.db", ".DS_Store"]

html_theme = "sphinx_rtd_theme"
html_context = {
    "display_github": True,
    "github_user": "zlatanstajic",
    "github_repo": "kiyosaki",
    "github_version": "master",
    "conf_py_path": "/docs/",
}
myst_heading_anchors = 3
