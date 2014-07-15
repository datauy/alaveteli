# The PublicBodyCategories structure works like this:
# [
#   "Main category name",
#       [ "tag_to_use_as_category", "Sub category title", "sentence that can describes things in this subcategory" ],
#       [ "another_tag", "Second sub category title", "another descriptive sentence for things in this subcategory"],
#   "Another main category name",
#       [ "another_tag_2", "Another sub category title", "another descriptive sentence"]
# ])
#
# DO NOT EDIT THIS FILE! It should be overridden in a custom theme.
# See doc/THEMES.md for more info

PublicBodyCategories.add(:es, [
    "Silly ministries",
        [ "useless_agency", "Los useless ministries", "el useless ministry" ],
        [ "lonely_agency", "Los lonely agencies", "el lonely agency"],
    "Popular agencies",
        [ "popular_agency", "Los popular agencies", "el lonely agency"],
        [ "spanish_agency", "Los random example", "el random example"]
])
