class AdminPublicBodyCategoryController < AdminController
    def index
        @locale = self.locale_from_params
        @category_headings = PublicBodyHeading.all
    end

    def new
        @category = PublicBodyCategory.new
        render :formats => [:html]
    end

    def edit
        @category = PublicBodyCategory.find(params[:id])
        @tagged_public_bodies = PublicBody.find_by_tag(@category.category_tag)
    end

    def update
        I18n.with_locale(I18n.default_locale) do
            @category = PublicBodyCategory.find(params[:id])
            if @category.update_attributes(params[:public_body_category])
                flash[:notice] = 'Category was successfully updated.'
            end
            render :action => 'edit'
        end
    end

    def create
        I18n.with_locale(I18n.default_locale) do
            @category = PublicBodyCategory.new(params[:public_body_category])
            if @category.save
                flash[:notice] = 'Category was successfully created.'
                redirect_to admin_category_index_url
            else
                render :action => 'new'
            end
        end
    end

    def destroy
        @locale = self.locale_from_params
        I18n.with_locale(@locale) do
            category = PublicBodyCategory.find(params[:id])

            if PublicBody.find_by_tag(category.category_tag).count > 0
                flash[:notice] = "There are authorities associated with this category, so can't destroy it"
                redirect_to admin_category_edit_url(category)
                return
            end

            category.destroy
            flash[:notice] = "Category was successfully destroyed."
            redirect_to admin_category_index_url
        end
    end
end
