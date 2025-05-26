// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { ChangeEvent } from "react";
import { getTranslations } from "../../../functions/translations";
import { MetadataInterface } from "../../../interface/WordpressInterface";
import {
  FormContentInterface,
  FormInterface,
  TableLineItemInterface,
} from "../../../interface/InputInterface";
import {
  DefaultFormState,
  getDefaultValue,
  getFlatFields,
  handleChangeInputGeneric,
  handleChangeSelectGeneric,
} from "../../../functions/form";
import { getListObjectSageMetadata } from "../../../functions/getMetadata";
import { FormContentComponent } from "../../component/form/FormContentComponent";
import {
  FArtglosseInterface,
  FGlossaireInterface,
} from "../../../interface/FArticleInterface";
import { FormInput } from "../../component/form/FormInput";
import { Tooltip } from "@mui/material";

let translations: any = getTranslations();

const articleMeta: MetadataInterface[] = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);

const fGlossaires: FGlossaireInterface[] = JSON.parse(
  $("[data-sage-fglossaires]").attr("data-sage-fglossaires") ?? "[]",
);

export const ArticleGlossairesComponent = React.forwardRef((props, ref) => {
  const prefix = "fArtglosses";
  const handleChange =
    (prop: keyof DefaultFormState) =>
    (event: React.ChangeEvent<HTMLInputElement>) => {
      handleChangeInputGeneric(event, prop, setValues);
    };

  const handleChangeSelect =
    (prop: keyof DefaultFormState) =>
    (event: ChangeEvent<HTMLSelectElement>) => {
      handleChangeSelectGeneric(event, prop, setValues);
    };

  const [fArtglosses, setFArtglosses] = React.useState<FArtglosseInterface[]>(
    getListObjectSageMetadata(prefix, articleMeta, "glNo"),
  );

  const getForm = (): FormInterface => {
    const formContent: FormContentInterface[] = [
      {
        props: {
          container: true,
          spacing: 1,
          sx: { p: 1 },
        },
        children: [
          {
            props: {
              size: { xs: 12 },
            },
            table: {
              headers: ["", translations.words.intitule, ""],
              fullWidth: true,
              canDelete: true,
              add: {
                table: {
                  headers: [translations.words.intitule],
                  key: "glNo",
                  addItem: (fGlossaire: FGlossaireInterface) => {
                    setFArtglosses((v) => {
                      return [
                        ...v,
                        {
                          glNo: fGlossaire.glNo,
                        },
                      ];
                    });
                  },
                  search: (item: FGlossaireInterface, search: string) => {
                    return (
                      item.glIntitule
                        .toLowerCase()
                        .includes(search.toLowerCase()) ||
                      item.glText.toLowerCase().includes(search.toLowerCase())
                    );
                  },
                  items: fGlossaires
                    .filter(
                      (fGlossaire) =>
                        fArtglosses.find(
                          (fArtglosse) =>
                            fArtglosse.glNo.toString() ===
                            fGlossaire.glNo.toString(),
                        ) === undefined,
                    )
                    .map((fGlossaire): TableLineItemInterface => {
                      return {
                        item: fGlossaire,
                        lines: [
                          {
                            Dom: <span>{fGlossaire.glIntitule}</span>,
                          },
                          {
                            Dom: (
                              <Tooltip title={fGlossaire.glText} arrow>
                                <p>
                                  {fGlossaire.glText.length > 102
                                    ? fGlossaire.glText.slice(0, 102) + "..."
                                    : fGlossaire.glText}
                                </p>
                              </Tooltip>
                            ),
                          },
                        ],
                      };
                    }),
                },
              },
              items: fArtglosses.map((fArtglosse): TableLineItemInterface => {
                const fGlossaire = fGlossaires.find(
                  (f) => f.glNo.toString() === fArtglosse.glNo.toString(),
                );
                return {
                  item: fGlossaire,
                  lines: [
                    {
                      field: {
                        name: prefix + "[" + fGlossaire.glNo + "].glNo",
                        DomField: FormInput,
                        type: "hidden",
                        hideLabel: true,
                      },
                    },
                    {
                      Dom: <span>{fGlossaire.glIntitule}</span>,
                    },
                    {
                      Dom: (
                        <Tooltip title={fGlossaire.glText} arrow>
                          <p>
                            {fGlossaire.glText.length > 102
                              ? fGlossaire.glText.slice(0, 102) + "..."
                              : fGlossaire.glText}
                          </p>
                        </Tooltip>
                      ),
                    },
                  ],
                };
              }),
            },
          },
        ],
      },
    ];
    const flatFields = getFlatFields(formContent);
    return {
      content: formContent,
      flatFields: flatFields,
      fieldNames: flatFields.map((f) => f.name),
    };
  };

  const [form, setForm] = React.useState<FormInterface>(getForm());

  const [values, setValues] = React.useState(getDefaultValue(form));

  React.useEffect(() => {
    setForm(getForm());
  }, [fArtglosses]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <FormContentComponent
      content={form.content}
      values={values}
      handleChange={handleChange}
      transPrefix="fArticles"
      handleChangeSelect={handleChangeSelect}
    />
  );
});
