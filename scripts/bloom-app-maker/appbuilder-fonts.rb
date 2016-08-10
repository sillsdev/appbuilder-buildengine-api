require 'nokogiri'

class AppBuilderFont
  attr_accessor :familyName, :fileName, :weight, :style
  def initialize(family_name, file_name, weight, style)
    self.familyName = family_name
    self.fileName = file_name
    self.weight = weight
    self.style = style
  end
end

class AppBuilderFonts
  attr_accessor :fonts

  def initialize
    self.fonts = []
  end

  def add_font(family_name, file_name, weight = 'normal', style = 'normal')
    font = AppBuilderFont.new(family_name, file_name, weight, style)
    self.fonts << font
  end

  def to_xml
    builder = Nokogiri::XML::Builder.new { |xml|
      xml.fonts {
        @fonts.each do |font|
          xml.send('font', :family => font.familyName) {
            xml.filename font.fileName
            xml.send('style-spec', 'property' => 'font-weight', 'value' => font.weight)
            xml.send('style-spec', 'property' => 'font-style', 'value' => font.style)
          }
        end
      }
    }
    builder.to_xml
  end
end

# fs = AppBuilderFonts.new
# fs.add_font('Comic Sans', 'comic.ttf')
# fs.add_font('Comic Sans', 'comicbd.ttf', 'bold')
# fs.add_font('Times New Roman', 'times.ttf')
# fs.add_font('Times New Roman', 'timesbd.ttf', 'bold')
# fs.add_font('Times New Roman', 'timesi.ttf', 'normal', 'italic')
# fs.add_font('Times New Roman', 'timesbi.ttf', 'bold', 'italic')
# File.write('fonts.xml', fs.to_xml)
