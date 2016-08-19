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
  attr_accessor :fontmap

  def initialize
    self.fontmap = Hash.new
  end

  def add_font(family_name, file_name, bold = false, italic = false)
    weight = bold ? 'bold' : 'normal'
    style = italic ? 'italic' : 'normal'
    font = AppBuilderFont.new(family_name, file_name, weight, style)
    unless self.fontmap.has_key?(family_name)
      self.fontmap[family_name] = []
    end
    self.fontmap[family_name] << font
  end

  def to_xml
    builder = Nokogiri::XML::Builder.new do |xml|
      xml.fonts {
        @fontmap.each do |family_name, fonts|
          xml.send('font-family', :name => family_name) {
            fonts.each do |font|
              xml.font {
                xml.filename font.fileName
                xml.send('style-spec', 'property' => 'font-weight', 'value' => font.weight)
                xml.send('style-spec', 'property' => 'font-style', 'value' => font.style)
              }
            end
          }
        end
      }
    end
    builder.to_xml
  end

  def count
    return self.fontmap.reduce(0) {|sum, (name, fonts)| sum += fonts.count}
  end
end

# fontdir = '/usr/share/fonts/truetype/msttcorefonts'
# fs = AppBuilderFonts.new
# fs.add_font('Comic Sans', "#{fontdir}/comic.ttf")
# fs.add_font('Comic Sans', "#{fontdir}/comicbd.ttf", true)
# fs.add_font('Times New Roman', "#{fontdir}/times.ttf")
# fs.add_font('Times New Roman', "#{fontdir}/timesbd.ttf", true)
# fs.add_font('Times New Roman', "#{fontdir}/timesi.ttf", false, true)
# fs.add_font('Times New Roman', "#{fontdir}/timesbi.ttf", true, true)
# puts "Count: #{fs.count}"
# File.write('fonts.xml', fs.to_xml)
